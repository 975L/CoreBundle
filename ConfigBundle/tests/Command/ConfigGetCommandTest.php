<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\ConfigGetCommand;
use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\VaultEncryptor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigGetCommandTest extends TestCase
{
    private const VAULT_KEY = 'a-test-vault-key';

    private function createConfig(string $slug, ?string $value = null, bool $isSensitive = false, string $kind = Config::TYPE_TEXT): Config
    {
        return (new Config())->setSlug($slug)->setLabel($slug)->setKind($kind)->setIsSensitive($isSensitive)->setValue($value);
    }

    private function createRepository(array $configs): ConfigRepository
    {
        $indexed = [];
        foreach ($configs as $config) {
            $indexed[$config->getSlug()] = $config;
        }

        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findOneBySlug')->willReturnCallback(fn (string $slug) => $indexed[$slug] ?? null);
        $repository->method('findBySlugPrefix')->willReturnCallback(
            fn (string $prefix) => array_values(array_filter($indexed, fn (string $slug) => str_starts_with($slug, $prefix), ARRAY_FILTER_USE_KEY))
        );

        return $repository;
    }

    private function createTester(array $configs, ?string $vaultKey = self::VAULT_KEY): CommandTester
    {
        return new CommandTester(new ConfigGetCommand(
            $this->createRepository($configs),
            new VaultEncryptor($vaultKey),
        ));
    }

    public function testExecuteFailsOnUnknownSlug(): void
    {
        $tester = $this->createTester([$this->createConfig('site-name', 'My Site')]);
        $tester->execute(['slug' => 'site-nam']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown config entry', $tester->getDisplay());
    }

    public function testExecuteFailsWhenPatternMatchesNothing(): void
    {
        $tester = $this->createTester([$this->createConfig('site-name', 'My Site')]);
        $tester->execute(['slug' => 'shop-*']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No config entry matches', $tester->getDisplay());
    }

    public function testExecuteDisplaysTheValueOfOneEntry(): void
    {
        $tester = $this->createTester([$this->createConfig('site-name', 'My Site')]);
        $tester->execute(['slug' => 'site-name']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('My Site', $tester->getDisplay());
    }

    public function testExecuteDisplaysEmptyValueAsSuch(): void
    {
        $tester = $this->createTester([$this->createConfig('site-backup-offsite-target')]);
        $tester->execute(['slug' => 'site-backup-offsite-target']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('(empty)', $tester->getDisplay());
    }

    public function testExecuteDisplaysEveryEntryMatchingThePattern(): void
    {
        $tester = $this->createTester([
            $this->createConfig('site-backup-offsite-target', 'storagebox:975l.com'),
            $this->createConfig('site-backup-offsite-keep-days', '15', false, Config::TYPE_INT),
            $this->createConfig('site-name', 'My Site'),
        ]);
        $tester->execute(['slug' => 'site-backup-offsite*']);

        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('storagebox:975l.com', $display);
        $this->assertStringContainsString('15', $display);
        $this->assertStringNotContainsString('My Site', $display);
    }

    public function testExecuteAcceptsTheSqlWildcardAsPattern(): void
    {
        $tester = $this->createTester([$this->createConfig('site-backup-offsite-target', 'storagebox:975l.com')]);
        $tester->execute(['slug' => 'site-backup-offsite%']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('storagebox:975l.com', $tester->getDisplay());
    }

    public function testExecuteMasksSensitiveValueByDefault(): void
    {
        $encryptor = new VaultEncryptor(self::VAULT_KEY);
        $tester = $this->createTester([$this->createConfig('site-backup-db-password', $encryptor->encrypt('s3cret'), true)]);
        $tester->execute(['slug' => 'site-backup-db-password']);

        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('********', $display);
        $this->assertStringNotContainsString('s3cret', $display);
    }

    public function testExecuteDecryptsSensitiveValueWithShowSensitive(): void
    {
        $encryptor = new VaultEncryptor(self::VAULT_KEY);
        $tester = $this->createTester([$this->createConfig('site-backup-db-password', $encryptor->encrypt('s3cret'), true)]);
        $tester->execute(['slug' => 'site-backup-db-password', '--show-sensitive' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('s3cret', $tester->getDisplay());
    }

    public function testExecuteReportsAnUndefinedVaultKey(): void
    {
        $encryptor = new VaultEncryptor(self::VAULT_KEY);
        $tester = $this->createTester([$this->createConfig('site-backup-db-password', $encryptor->encrypt('s3cret'), true)], null);
        $tester->execute(['slug' => 'site-backup-db-password', '--show-sensitive' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('C975L_VAULT_KEY is not defined', $tester->getDisplay());
    }

    public function testExecuteReportsAValueEncryptedWithAnotherKey(): void
    {
        $encryptor = new VaultEncryptor('another-vault-key');
        $tester = $this->createTester([$this->createConfig('site-backup-db-password', $encryptor->encrypt('s3cret'), true)]);
        $tester->execute(['slug' => 'site-backup-db-password', '--show-sensitive' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('another C975L_VAULT_KEY', $tester->getDisplay());
    }

    public function testExecutePrintsTheValueAloneWithRaw(): void
    {
        $tester = $this->createTester([$this->createConfig('site-name', 'My Site')]);
        $tester->execute(['slug' => 'site-name', '--raw' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame('My Site', trim($tester->getDisplay()));
    }

    // A mask printed alone would be captured by a "$(...)" as if it were the value, with a success code letting the script carry on with it
    public function testExecuteFailsRatherThanPrintTheMaskWithRaw(): void
    {
        $encryptor = new VaultEncryptor(self::VAULT_KEY);
        $tester = $this->createTester([$this->createConfig('site-backup-db-password', $encryptor->encrypt('s3cret'), true)]);
        $tester->execute(['slug' => 'site-backup-db-password', '--raw' => true], ['capture_stderr_separately' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertSame('', trim($tester->getDisplay()));
        $this->assertStringContainsString('--show-sensitive', $tester->getErrorOutput());
    }

    // The whole read fails rather than printing the non-sensitive part alone, a partial output being read as the complete one
    public function testExecuteFailsOnAPatternHoldingASensitiveEntryWithRaw(): void
    {
        $encryptor = new VaultEncryptor(self::VAULT_KEY);
        $tester = $this->createTester([
            $this->createConfig('site-backup-offsite-target', 'storagebox:975l.com'),
            $this->createConfig('site-backup-offsite-password', $encryptor->encrypt('s3cret'), true),
        ]);
        $tester->execute(['slug' => 'site-backup-offsite*', '--raw' => true], ['capture_stderr_separately' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertSame('', trim($tester->getDisplay()));
    }

    public function testExecutePrintsTheSensitiveValueAloneWithRawAndShowSensitive(): void
    {
        $encryptor = new VaultEncryptor(self::VAULT_KEY);
        $tester = $this->createTester([$this->createConfig('site-backup-db-password', $encryptor->encrypt('s3cret'), true)]);
        $tester->execute(['slug' => 'site-backup-db-password', '--raw' => true, '--show-sensitive' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame('s3cret', trim($tester->getDisplay()));
    }

    // An entry that is sensitive but holds nothing has no secret to leak, and refusing it would break a script reading a config that is simply not set yet
    public function testExecutePrintsAnEmptySensitiveEntryWithRaw(): void
    {
        $tester = $this->createTester([$this->createConfig('site-backup-db-password', null, true)]);
        $tester->execute(['slug' => 'site-backup-db-password', '--raw' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame('', trim($tester->getDisplay()));
    }
}
