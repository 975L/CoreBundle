<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use HeadlessChromium\BrowserFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

// What a launch leaves for the end of the PHP process, run in a child process of its own since the failure being described only shows once that process exits
#[Group('browser')]
class HeadlessChromeTest extends TestCase
{
    private const string CHROME = '/usr/bin/google-chrome';

    protected function setUp(): void
    {
        if (!class_exists(BrowserFactory::class) || !is_executable(self::CHROME)) {
            $this->markTestSkipped('chrome-php/chrome and google-chrome are needed to launch a browser.');
        }
    }

    // A launch timing out between its connection and its browser, the very one that left chrome-php's shutdown kill calling sendCloseMessage() on null
    public function testALaunchTimingOutLeavesTheProcessExitingCleanly(): void
    {
        [$code, $output] = $this->runChild("try {\n    HeadlessChrome::launch('" . self::CHROME . "', ['headless' => true, 'noSandbox' => true, 'sendSyncDefaultTimeout' => 0]);\n} catch (\\Exception \$exception) {\n    echo 'caught ', \$exception::class;\n}");

        $this->assertStringContainsString('caught HeadlessChromium\Exception\OperationTimedOut', $output);
        $this->assertStringNotContainsString('Fatal', $output);
        $this->assertSame(0, $code, $output);
    }

    // A browser launched and never closed, taken down with the process
    public function testALaunchedBrowserIsKilledWithTheProcess(): void
    {
        [$code, $output] = $this->runChild("echo HeadlessChrome::launch('" . self::CHROME . "', ['headless' => true, 'noSandbox' => true])->getConnection()->isConnected() ? 'connected' : 'not connected';");

        $this->assertSame('connected', $output);
        $this->assertSame(0, $code, $output);
    }

    // The code given run by a PHP of its own, after the autoloader, answering its exit code and everything it printed
    /** @return array{int, string} */
    private function runChild(string $code): array
    {
        $script = sys_get_temp_dir() . '/chrome-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($script, "<?php\nrequire " . var_export(\dirname(__DIR__, 3) . '/vendor/autoload.php', true) . ";\nuse c975L\\UiBundle\\Service\\HeadlessChrome;\n" . $code . "\n");

        try {
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $lines, $exitCode);
        } finally {
            unlink($script);
        }

        return [$exitCode, implode("\n", $lines)];
    }
}
