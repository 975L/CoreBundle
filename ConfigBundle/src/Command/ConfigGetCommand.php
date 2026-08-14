<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\VaultEncryptor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console counterpart of c975l:config:set, reading what c975l:config:set writes. Values are read
 * from the database rather than from ConfigService, whose cache is exactly what a diagnostic is
 * meant to see past.
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(name: 'c975l:config:get', description: 'Displays the value of one or more config entries', help: <<<'TXT'
Reads a single entry:

  <info>php %command.full_name% site-name</info>

Reads every entry of a family, the trailing * matching any end of slug and the quotes
keeping the shell from expanding it against the current directory:

  <info>php %command.full_name% 'site-backup-offsite*'</info>

Feeds a shell variable, the value being printed alone:

  <info>SITE=$(php %command.full_name% site-name --raw)</info>

Sensitive entries are masked unless --show-sensitive is passed, which decrypts them
with C975L_VAULT_KEY. An unknown slug, or a prefix matching nothing, exits in failure.
So does --raw on an entry it would have to mask: the mask would be captured as if it
were the value, where a failure stops the script.
TXT)]
class ConfigGetCommand extends Command
{
    // What a sensitive value shows instead of itself, of a length telling nothing about the secret's own
    private const string MASK = '********';

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly VaultEncryptor $vaultEncryptor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('slug', InputArgument::REQUIRED, 'Slug of the config entry to read, or a prefix ending with * (e.g. site-backup-offsite*)')
            ->addOption('show-sensitive', null, InputOption::VALUE_NONE, 'Displays sensitive values in clear text instead of masking them')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'Prints the values alone, one per line, without table nor decoration')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $slug = (string) $input->getArgument('slug');
        $configs = $this->findConfigs($slug);

        if ([] === $configs) {
            $io->error($this->isPattern($slug)
                ? 'No config entry matches: ' . $slug
                : 'Unknown config entry: ' . $slug . ' (run c975l:config:load-all first if the bundle was just installed)');

            return Command::FAILURE;
        }

        $showSensitive = (bool) $input->getOption('show-sensitive');

        // Raw output goes straight to the output, SymfonyStyle's own formatting being what a script piping this would have to strip back out
        if ($input->getOption('raw')) {
            // A mask printed alone is captured by a "$(...)" as if it were the value, with nothing in the exit code to tell it apart - so nothing is printed at all and the script stops on the failure, the same as an unknown slug does
            $masked = $showSensitive ? [] : array_filter($configs, static fn (Config $config): bool => $config->getIsSensitive() && null !== $config->getValue() && '' !== $config->getValue());
            if ([] !== $masked) {
                // On stderr, so a "$(...)" capturing this command gets an empty string rather than the error text itself
                $io->getErrorStyle()->error('Sensitive entry: ' . implode(', ', array_map(static fn (Config $config): string => $config->getSlug(), $masked)) . ' - pass --show-sensitive to read it, or drop --raw');

                return Command::FAILURE;
            }

            foreach ($configs as $config) {
                $output->writeln($this->display($config, $showSensitive));
            }

            return Command::SUCCESS;
        }

        $io->table(
            ['Slug', 'Kind', 'Value'],
            array_map(function (Config $config) use ($showSensitive): array {
                $value = $this->display($config, $showSensitive);

                return [$config->getSlug(), $config->getKind(), '' === $value ? '(empty)' : $value];
            }, $configs),
        );

        return Command::SUCCESS;
    }

    // The entries to display, a trailing wildcard matching a whole family of slugs and its absence a single entry
    private function findConfigs(string $slug): array
    {
        if ($this->isPattern($slug)) {
            return $this->configRepository->findBySlugPrefix(rtrim($slug, '*%'));
        }

        $config = $this->configRepository->findOneBySlug($slug);

        return null === $config ? [] : [$config];
    }

    // A "%" is accepted next to the "*", the SQL wildcard being the one a hand-written query would have used
    private function isPattern(string $slug): bool
    {
        return str_ends_with($slug, '*') || str_ends_with($slug, '%');
    }

    // The value as displayed, a secret never reaching the console output nor a CI log without being asked for
    private function display(Config $config, bool $showSensitive): string
    {
        $value = $config->getValue();

        if (null === $value || '' === $value) {
            return '';
        }

        if (!$config->getIsSensitive()) {
            return $value;
        }

        if (!$showSensitive) {
            return self::MASK;
        }

        if (!$this->vaultEncryptor->isEncrypted($value)) {
            return $value;
        }

        if (!$this->vaultEncryptor->isKeyDefined()) {
            return '(encrypted, C975L_VAULT_KEY is not defined)';
        }

        try {
            return $this->vaultEncryptor->decrypt($value);
        } catch (\RuntimeException) {
            return '(encrypted with another C975L_VAULT_KEY)';
        }
    }
}
