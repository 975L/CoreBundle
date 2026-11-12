<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\MySqlOptionsFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class MySqlOptionsFileTest extends TestCase
{
    private const string PASSWORD = 'Ab#9x\tq"z';

    // Quoted and escaped, "#" no longer opening a comment nor "\" an escape
    public function testEveryValueIsQuotedAndEscaped(): void
    {
        $this->assertSame(
            "[client]\nhost=\"db.example.test\"\nuser=\"site\"\npassword=\"Ab#9x\\\\tq\\\"z\"\n",
            MySqlOptionsFile::content('db.example.test', 'site', self::PASSWORD),
        );
    }

    // The client's own parser reads the password back exactly as stored, the only proof that counts
    public function testTheClientReadsThePasswordBackUnchanged(): void
    {
        if (null === new ExecutableFinder()->find('my_print_defaults')) {
            $this->markTestSkipped('my_print_defaults is not available');
        }

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): string => 'site-backup-db-password' === $key ? self::PASSWORD : 'site');
        $path = MySqlOptionsFile::write($configService, 'options_test_');

        try {
            $process = new Process(['my_print_defaults', '--defaults-file=' . $path, 'client']);
            $process->mustRun();

            $this->assertContains('--password=' . self::PASSWORD, explode("\n", trim($process->getOutput())));
            $this->assertSame('0600', substr(\sprintf('%o', fileperms($path)), -4));
        } finally {
            unlink($path);
        }
    }
}
