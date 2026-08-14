<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\EncryptSensitiveCommand;
use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\VaultEncryptor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class EncryptSensitiveCommandTest extends TestCase
{
    // A row as the aes-256-cbc this bundle no longer writes stored it, under the key used throughout - captured from that version, so what it proves is that a site's existing rows convert, not that two implementations agree
    private const string LEGACY_PAYLOAD = 'C975L:2w9FrnRq99FtTi9eKuCCLOZ6aWWRiCK0FVqd2zyk9WYR9o7sgHCO8yp+tFVZ1YNv';

    private const string LEGACY_PAYLOAD_PLAIN = 'sk_live_secret_value';

    private function createConfig(string $slug, ?string $value, bool $isSensitive = true): Config
    {
        return new Config()->setSlug($slug)->setLabel($slug)->setIsSensitive($isSensitive)->setValue($value);
    }

    private function createRepository(array $configs): ConfigRepository
    {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findAll')->willReturn($configs);

        return $repository;
    }

    private function createTester(
        ConfigRepository $repository,
        VaultEncryptor $vaultEncryptor,
        EntityManagerInterface $manager,
    ): CommandTester {
        return new CommandTester(new EncryptSensitiveCommand($repository, $vaultEncryptor, $manager));
    }

    public function testExecuteFailsWhenVaultKeyIsNotDefined(): void
    {
        $tester = $this->createTester(
            $this->createRepository([]),
            new VaultEncryptor(null),
            $this->createStub(EntityManagerInterface::class),
        );
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('C975L_VAULT_KEY is not defined', $tester->getDisplay());
    }

    public function testExecuteReportsWhenNoSensitiveConfigExists(): void
    {
        $configs = [$this->createConfig('site-name', 'My Site', isSensitive: false)];

        $tester = $this->createTester(
            $this->createRepository($configs),
            new VaultEncryptor('a-test-vault-key'),
            $this->createStub(EntityManagerInterface::class),
        );
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('No sensitive config found', $tester->getDisplay());
    }

    public function testExecuteEncryptsPlainTextSensitiveValuesAndFlushes(): void
    {
        $config = $this->createConfig('api-key', 'plain-secret');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($config);
        $manager->expects($this->once())->method('flush');

        $vaultEncryptor = new VaultEncryptor('a-test-vault-key');
        $tester = $this->createTester($this->createRepository([$config]), $vaultEncryptor, $manager);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame('plain-secret', $vaultEncryptor->decrypt($config->getValue()));
        $this->assertStringContainsString('1 encrypted, 0 converted, 0 skipped', $tester->getDisplay());
    }

    public function testExecuteSkipsAlreadyEncryptedValues(): void
    {
        $vaultEncryptor = new VaultEncryptor('a-test-vault-key');
        $config = $this->createConfig('api-key', $vaultEncryptor->encrypt('already-encrypted'));

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('persist');
        $manager->expects($this->once())->method('flush');

        $tester = $this->createTester($this->createRepository([$config]), $vaultEncryptor, $manager);
        $tester->execute([]);

        $this->assertStringContainsString('0 encrypted, 0 converted, 1 skipped', $tester->getDisplay());
    }

    public function testExecuteSkipsEmptyValues(): void
    {
        $config = $this->createConfig('api-key', null);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('persist');
        $manager->expects($this->once())->method('flush');

        $tester = $this->createTester(
            $this->createRepository([$config]),
            new VaultEncryptor('a-test-vault-key'),
            $manager,
        );
        $tester->execute([]);

        $this->assertStringContainsString('0 encrypted, 0 converted, 1 skipped', $tester->getDisplay());
    }

    // The conversion a deployment runs: the row is rewritten in the format the encryptor now writes, holding the same secret under the same key
    public function testExecuteConvertsAValueStoredInTheLegacyFormat(): void
    {
        $vaultEncryptor = new VaultEncryptor('a-test-vault-key');
        $config = $this->createConfig('api-key', self::LEGACY_PAYLOAD);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($config);
        $manager->expects($this->once())->method('flush');

        $tester = $this->createTester($this->createRepository([$config]), $vaultEncryptor, $manager);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(self::LEGACY_PAYLOAD_PLAIN, $vaultEncryptor->decrypt($config->getValue()));
        $this->assertFalse($vaultEncryptor->isLegacyEncrypted($config->getValue()));
        $this->assertStringContainsString('0 encrypted, 1 converted, 0 skipped', $tester->getDisplay());
    }

    // Run again on the site it just converted, it writes nothing - which is what lets it sit in a deployment rather than be run by hand once
    public function testExecuteConvertsNothingOnASecondRun(): void
    {
        $vaultEncryptor = new VaultEncryptor('a-test-vault-key');
        $config = $this->createConfig('api-key', $vaultEncryptor->encrypt(self::LEGACY_PAYLOAD_PLAIN));

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('persist');

        $tester = $this->createTester($this->createRepository([$config]), $vaultEncryptor, $manager);
        $tester->execute([]);

        $this->assertStringContainsString('0 encrypted, 0 converted, 1 skipped', $tester->getDisplay());
    }

    // A secret lost to a changed key must not hold a site's release back: the row is reported and left as it stands, and the command still succeeds
    public function testExecuteLeavesAValueItCannotReadUntouchedAndSucceeds(): void
    {
        $config = $this->createConfig('api-key', new VaultEncryptor('another-key')->encrypt('secret'));

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('persist');
        $manager->expects($this->once())->method('flush');

        $tester = $this->createTester(
            $this->createRepository([$config]),
            new VaultEncryptor('a-test-vault-key'),
            $manager,
        );
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('not readable with this C975L_VAULT_KEY', $tester->getDisplay());
        $this->assertStringContainsString('0 encrypted, 0 converted, 1 skipped', $tester->getDisplay());
    }

    public function testExecuteIgnoresNonSensitiveConfigsEntirely(): void
    {
        $sensitive = $this->createConfig('api-key', 'secret', isSensitive: true);
        $nonSensitive = $this->createConfig('site-name', 'My Site', isSensitive: false);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($sensitive);

        $tester = $this->createTester(
            $this->createRepository([$sensitive, $nonSensitive]),
            new VaultEncryptor('a-test-vault-key'),
            $manager,
        );
        $tester->execute([]);

        $this->assertStringContainsString('1 encrypted, 0 converted, 0 skipped', $tester->getDisplay());
    }
}
