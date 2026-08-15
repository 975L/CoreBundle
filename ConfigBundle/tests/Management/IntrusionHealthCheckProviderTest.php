<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;
use c975L\ConfigBundle\Management\IntrusionHealthCheckProvider;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\EnvironmentProbe;
use c975L\ConfigBundle\Service\PrivilegedAccountCounter;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class IntrusionHealthCheckProviderTest extends TestCase
{
    private const string SITE_ROOT = 'https://example.com';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/intrusion-health-check-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public/medias');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    /**
     * @param array<string, string> $uploadedFiles path under public/medias => contents
     */
    private function createProvider(array $uploadedFiles = [], int $admins = 0, ?int $previousAccounts = null, string $adminRole = 'ROLE_ADMIN', ?string $siteRoot = self::SITE_ROOT): IntrusionHealthCheckProvider
    {
        foreach ($uploadedFiles as $path => $contents) {
            file_put_contents($this->projectDir . '/public/medias/' . $path, $contents);
        }

        $backupPathProvider = $this->createStub(BackupPathProviderInterface::class);
        $backupPathProvider->method('getBackupPaths')->willReturn([
            new BackupPath('public/medias'),
            // A declared path that is not deployed here: dropped rather than reported, a directory that does not exist holding nothing
            new BackupPath('private/invoices'),
        ]);

        $previousRow = null;
        if (null !== $previousAccounts) {
            $previousRow = new HealthCheckResult()
                ->setKind(IntrusionHealthCheckProvider::KIND)
                ->setUrl(self::SITE_ROOT . '#accounts')
                ->setStatus(HealthCheckResult::STATUS_OK)
                ->setSummary('previous run')
                ->setDetails(['accounts' => $previousAccounts])
                ->setCheckedAt(new \DateTimeImmutable('2026-08-01 03:00:00'));
        }

        $healthCheckResultRepository = $this->createStub(HealthCheckResultRepository::class);
        $healthCheckResultRepository->method('findLatestByUrlAndKind')->willReturn($previousRow);

        $privilegedAccountCounter = $this->createStub(PrivilegedAccountCounter::class);
        $privilegedAccountCounter->method('countHolding')->willReturn($admins);

        // Reads are taken as they come on the machine running the suite, except where a test needs one pinned - what matters is the row produced, not what this host allows
        $environmentProbe = $this->createStub(EnvironmentProbe::class);
        $environmentProbe->method('canExec')->willReturn(false);
        $environmentProbe->method('getBinaryVersion')->willReturn(null);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($adminRole);

        $siteUrlResolver = $this->createStub(SiteUrlResolver::class);
        $siteUrlResolver->method('siteRoot')->willReturn($siteRoot);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []) => $id . '|' . implode('|', $params)
        );

        return new IntrusionHealthCheckProvider(
            [$backupPathProvider],
            $healthCheckResultRepository,
            $privilegedAccountCounter,
            $environmentProbe,
            $configService,
            $siteUrlResolver,
            $translator,
            $this->projectDir,
        );
    }

    private function rowFor(array $rows, string $suffix): array
    {
        foreach ($rows as $row) {
            if (self::SITE_ROOT . $suffix === $row['url']) {
                return $row;
            }
        }

        $this->fail(sprintf('No row for "%s"', $suffix));
    }

    public function testGetKind(): void
    {
        $this->assertSame('intrusion', $this->createProvider()->getKind());
    }

    public function testEachTraceGetsItsOwnRowSoEachKeepsItsOwnHistory(): void
    {
        $rows = $this->createProvider()->runChecks();

        $this->assertCount(3, $rows);
    }

    public function testAnUploadDirectoryHoldingOnlyMediaIsClean(): void
    {
        $rows = $this->createProvider(['photo.webp' => 'binary'])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_OK, $this->rowFor($rows, '#uploads')['status']);
    }

    // Nothing legitimate writes an executable into an upload directory, so this is not a drift to look at when there is time
    public function testAPhpFileAmongTheUploadsIsAnError(): void
    {
        $rows = $this->createProvider(['shell.php' => '<?php'])->runChecks();
        $row = $this->rowFor($rows, '#uploads');

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $row['status']);
        $this->assertSame(['public/medias/shell.php'], $row['details']['files']);
    }

    // A webshell carries its danger in the middle of its name as readily as at the end, and .htaccess is an extension to no one but the server
    public function testADoubleExtensionAndAnHtaccessAreCaughtToo(): void
    {
        $rows = $this->createProvider(['shell.php.jpg' => '<?php', '.htaccess' => 'AddType'])->runChecks();
        $row = $this->rowFor($rows, '#uploads');

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $row['status']);
        $this->assertCount(2, $row['details']['files']);
    }

    // Reporting it clean would be a lie about a check that never ran
    public function testAnUncheckableWorkingTreeIsSkippedRatherThanPassed(): void
    {
        $rows = $this->createProvider()->runChecks();
        $row = $this->rowFor($rows, '#code');

        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $row['status']);
        $this->assertStringContainsString('label.health_check_intrusion_code_no_repository', $row['summary']);
    }

    public function testAFirstRunHasNothingToCompareAccountsAgainst(): void
    {
        $rows = $this->createProvider(admins: 1)->runChecks();
        $row = $this->rowFor($rows, '#accounts');

        $this->assertSame(HealthCheckResult::STATUS_OK, $row['status']);
        $this->assertSame(1, $row['details']['accounts']);
    }

    public function testAnAdditionalPrivilegedAccountIsAWarningNamingBothCounts(): void
    {
        $rows = $this->createProvider(admins: 2, previousAccounts: 1)->runChecks();
        $row = $this->rowFor($rows, '#accounts');

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $row['status']);
        $this->assertSame(['accounts' => 2, 'previous' => 1], $row['details']);
    }

    // An account removed is somebody doing their job, not a trace to raise
    public function testAnAccountRemovedIsNotReported(): void
    {
        $rows = $this->createProvider(admins: 1, previousAccounts: 3)->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_OK, $this->rowFor($rows, '#accounts')['status']);
    }

    public function testWithNoAdminRoleConfiguredTheAccountsRowIsSkipped(): void
    {
        $rows = $this->createProvider(adminRole: '')->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $this->rowFor($rows, '#accounts')['status']);
    }

    // Who they are is the site's business: the number moving on its own is the whole signal, and a row that carried a name would put one in a report built to leave the site
    public function testTheAccountsRowCarriesCountsAndNothingElse(): void
    {
        $rows = $this->createProvider(admins: 2, previousAccounts: 1)->runChecks();

        $this->assertSame(['accounts', 'previous'], array_keys($this->rowFor($rows, '#accounts')['details']));
    }

    public function testWithNoSiteUrlThereIsNothingToKeyARowOn(): void
    {
        $this->assertSame([], $this->createProvider(siteRoot: null)->runChecks());
    }
}
