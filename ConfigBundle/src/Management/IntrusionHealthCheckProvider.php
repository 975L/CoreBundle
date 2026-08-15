<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Attribute\AsHealthCheck;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\EnvironmentProbe;
use c975L\ConfigBundle\Service\PrivilegedAccountCounter;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// Looks for the traces an intrusion leaves rather than for the doors it came through - the other security checks here (see SecurityHeadersHealthCheckProvider, SecurityMisconfigurationHealthCheckProvider) all answer "is this closed", which says nothing about whether someone already walked in. Three traces, chosen for having no innocent explanation on a deployed site: an executable file where only uploads are written, a working tree that no longer matches what was deployed, and a privileged account more than the last run counted.
//
// None of them proves an intrusion on its own, and none is meant to: what they have in common is that a site nobody touched produces none of them, so any one of them is worth a look. A check that cannot run says so (skipped) rather than passing, silence being what this whole provider exists to break.
#[AsHealthCheck(frequency: AsHealthCheck::FREQUENCY_WEEKLY)]
class IntrusionHealthCheckProvider implements HealthCheckProviderInterface
{
    public const string KIND = 'intrusion';

    // Anything a web server can be talked into executing, whatever it was uploaded as. Extensions rather than mime types: a webshell is a text file by every measure but the one that matters, which is what the server does when the url is asked for
    private const array EXECUTABLE_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phtml', 'phar', 'pht',
        'cgi', 'pl', 'py', 'sh', 'htaccess',
    ];

    // Suffixes the three rows are keyed by, appended to the site root so each keeps its own history (results are stored per url and kind)
    private const string ROW_UPLOADS = '#uploads';
    private const string ROW_CODE = '#code';
    private const string ROW_ACCOUNTS = '#accounts';

    public function __construct(
        private readonly iterable $backupPathProviders,
        private readonly HealthCheckResultRepository $healthCheckResultRepository,
        private readonly PrivilegedAccountCounter $privilegedAccountCounter,
        private readonly EnvironmentProbe $environmentProbe,
        private readonly ConfigServiceInterface $configService,
        private readonly SiteUrlResolver $siteUrlResolver,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    public function runChecks(): array
    {
        $siteRoot = $this->siteUrlResolver->siteRoot();

        // Same guard as every site-wide check here: without a site url there is nothing to key a row on
        if (null === $siteRoot) {
            return [];
        }

        return [
            $this->checkUploadDirectories($siteRoot),
            $this->checkWorkingTree($siteRoot),
            $this->checkPrivilegedAccounts($siteRoot),
        ];
    }

    // Every directory a bundle declared as holding files no git clone and no dump can bring back (see BackupPathProviderInterface) - which is the same list as "everywhere this site writes what visitors and editors send it". Read off the providers rather than named here, so a bundle added later is covered without this class knowing it exists
    private function checkUploadDirectories(string $siteRoot): array
    {
        $found = [];

        foreach ($this->uploadDirectories() as $directory) {
            foreach ($this->executableFilesIn($directory) as $file) {
                $found[] = $file;
            }
        }

        if ([] === $found) {
            return $this->row($siteRoot . self::ROW_UPLOADS, HealthCheckResult::STATUS_OK, 'label.health_check_intrusion_uploads_clean');
        }

        // An error rather than a warning, and the only one here: nothing legitimate writes an executable into an upload directory, so this is not a drift to look at when there is time
        return $this->row(
            $siteRoot . self::ROW_UPLOADS,
            HealthCheckResult::STATUS_ERROR,
            'label.health_check_intrusion_uploads_executable',
            ['%count%' => \count($found), '%files%' => implode(', ', \array_slice($found, 0, 5))],
            ['files' => \array_slice($found, 0, 50)],
        );
    }

    // What is deployed against what was deployed. Only ever reads: a working tree is the one copy of the code an intruder has to modify to leave anything behind, and git already knows it file by file
    private function checkWorkingTree(string $siteRoot): array
    {
        // A site deployed by rsync, by an archive or by hand has no repository to compare against, and saying so is the honest answer - reporting it clean would be a lie about a check that never ran
        if (!is_dir($this->projectDir . '/.git')) {
            return $this->row($siteRoot . self::ROW_CODE, HealthCheckResult::STATUS_SKIPPED, 'label.health_check_intrusion_code_no_repository');
        }

        if (!$this->environmentProbe->canExec() || null === $this->environmentProbe->getBinaryVersion('git')) {
            return $this->row($siteRoot . self::ROW_CODE, HealthCheckResult::STATUS_SKIPPED, 'label.health_check_intrusion_code_unavailable');
        }

        $output = [];
        $returnVar = 1;

        // Nothing from outside reaches this: one fixed subcommand, and the only interpolated value is the project directory the kernel itself was booted from. "--porcelain" is the stable, parseable form, and "--untracked-files=no" keeps the answer to what was deployed and has since changed - what the site writes as it runs is untracked, and listing it would make this row a permanent warning on every deployed site
        @\exec(\sprintf('git -C %s status --porcelain --untracked-files=no 2>/dev/null', \escapeshellarg($this->projectDir)), $output, $returnVar);

        if (0 !== $returnVar) {
            return $this->row($siteRoot . self::ROW_CODE, HealthCheckResult::STATUS_SKIPPED, 'label.health_check_intrusion_code_unavailable');
        }

        $changed = array_values(array_filter(array_map(trim(...), $output)));

        if ([] === $changed) {
            return $this->row($siteRoot . self::ROW_CODE, HealthCheckResult::STATUS_OK, 'label.health_check_intrusion_code_clean');
        }

        return $this->row(
            $siteRoot . self::ROW_CODE,
            HealthCheckResult::STATUS_WARNING,
            'label.health_check_intrusion_code_modified',
            ['%count%' => \count($changed), '%files%' => implode(', ', \array_slice($changed, 0, 5))],
            ['changed' => \array_slice($changed, 0, 50)],
        );
    }

    // How many accounts hold the role that opens the back office, against how many held it last time. A count rather than a list: who they are is the site's business, and the number moving on its own is the whole signal
    private function checkPrivilegedAccounts(string $siteRoot): array
    {
        $adminRole = (string) $this->configService->get('site-role-admin');

        if ('' === $adminRole) {
            return $this->row($siteRoot . self::ROW_ACCOUNTS, HealthCheckResult::STATUS_SKIPPED, 'label.health_check_intrusion_accounts_no_role');
        }

        $count = $this->privilegedAccountCounter->countHolding($adminRole);
        $previous = $this->previousAccountCount($siteRoot);

        // A first run has nothing to compare against, and a count that dropped is an account removed - which is somebody doing their job, not a trace to raise
        if (null === $previous || $count <= $previous) {
            return $this->row(
                $siteRoot . self::ROW_ACCOUNTS,
                HealthCheckResult::STATUS_OK,
                'label.health_check_intrusion_accounts_stable',
                ['%count%' => $count],
                ['accounts' => $count],
            );
        }

        return $this->row(
            $siteRoot . self::ROW_ACCOUNTS,
            HealthCheckResult::STATUS_WARNING,
            'label.health_check_intrusion_accounts_gained',
            ['%count%' => $count, '%previous%' => $previous],
            ['accounts' => $count, 'previous' => $previous],
        );
    }

    // The count this same row carried last run, from the results the runner already persists - no state of its own to keep, and none to migrate
    private function previousAccountCount(string $siteRoot): ?int
    {
        $result = $this->healthCheckResultRepository->findLatestByUrlAndKind($siteRoot . self::ROW_ACCOUNTS, self::KIND);
        $previous = ($result?->getDetails() ?? [])['accounts'] ?? null;

        return \is_int($previous) ? $previous : null;
    }

    /** @return list<string> the declared directories, as absolute paths, the ones that are not directories (a single declared file, a path not deployed) being dropped */
    private function uploadDirectories(): array
    {
        $directories = [];

        foreach ($this->backupPathProviders as $provider) {
            foreach ($provider->getBackupPaths() as $backupPath) {
                $absolute = $this->projectDir . '/' . trim($backupPath->path, '/');

                if (is_dir($absolute)) {
                    $directories[] = $absolute;
                }
            }
        }

        return array_values(array_unique($directories));
    }

    /** @return list<string> paths relative to the project directory, so a row names what a maintainer can go and look at */
    private function executableFilesIn(string $directory): array
    {
        $found = [];

        // CATCH_GET_CHILD so a sub-directory that cannot be read (permissions, a mount gone, a directory removed mid-walk) is skipped instead of aborting the walk - the alternative is an exception the runner turns into a failed command, losing the rows every other provider already produced
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
            \RecursiveIteratorIterator::CATCH_GET_CHILD,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            // Both halves matter: ".htaccess" is an extension to no one but is read as configuration by the server, and "shell.php.jpg" carries its danger in the middle rather than at the end
            $name = strtolower($file->getFilename());
            foreach (self::EXECUTABLE_EXTENSIONS as $extension) {
                if (str_ends_with($name, '.' . $extension) || str_contains($name, '.' . $extension . '.')) {
                    $found[] = str_replace($this->projectDir . '/', '', $file->getPathname());

                    break;
                }
            }
        }

        return $found;
    }

    private function row(string $url, string $status, string $translationId, array $params = [], array $details = []): array
    {
        return [
            'url' => $url,
            'label' => self::KIND,
            'status' => $status,
            'summary' => $this->translator->trans($translationId, $params, 'config'),
            'details' => $details,
        ];
    }
}
