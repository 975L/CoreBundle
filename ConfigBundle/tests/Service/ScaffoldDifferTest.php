<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\BundleLocator;
use c975L\ConfigBundle\Service\ScaffoldDiffer;
use c975L\ConfigBundle\Service\ScaffoldInstaller;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ScaffoldDifferTest extends TestCase
{
    private string $projectDir;

    private string $clonesDir;

    // Everything here reads a real history, git being the only thing that holds the version a file was delivered from
    protected function setUp(): void
    {
        if (null === new ExecutableFinder()->find('git')) {
            $this->markTestSkipped('git is not available');
        }

        $base = sys_get_temp_dir() . '/c975l-scaffold-differ-test-' . uniqid();
        $this->projectDir = $base . '/site';
        $this->clonesDir = $base . '/clones';
        mkdir($this->projectDir, 0775, true);
        mkdir($this->clonesDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(\dirname($this->projectDir));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function bundleLocator(): BundleLocator
    {
        $metadata = [];
        foreach (glob($this->projectDir . '/vendor/c975l/*', \GLOB_ONLYDIR) ?: [] as $directory) {
            $metadata[basename($directory)] = ['path' => $directory, 'namespace' => 'c975L\\Test'];
        }

        return new BundleLocator($metadata);
    }

    private function differ(): ScaffoldDiffer
    {
        return new ScaffoldDiffer(new ScaffoldInstaller($this->bundleLocator(), $this->projectDir), $this->projectDir);
    }

    private function write(string $path, string $content): void
    {
        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0775, true);
        }

        file_put_contents($path, $content);
    }

    // The version of a file the bundles currently ship
    private function addScaffoldFile(string $relativePath, string $content): void
    {
        $this->write($this->projectDir . '/vendor/c975l/config-bundle/scaffold/' . $relativePath, $content);
    }

    // What the manifest says was last delivered here, which is the base the differ goes looking for
    private function recordAsDelivered(string $relativePath, string $content): void
    {
        file_put_contents($this->projectDir . '/.c975l-scaffold.json', json_encode([$relativePath => hash('sha256', $content)]));
    }

    // A repository holding one file, committed as it stands - the site's own history, or a bundle's clone
    private function commit(string $repository, string $relativePath, string $content): void
    {
        $this->write($repository . '/' . $relativePath, $content);

        if (!is_dir($repository . '/.git')) {
            new Process(['git', 'init', '-q'], $repository)->mustRun();
        }

        new Process(['git', 'add', '--', $relativePath], $repository)->mustRun();
        new Process(['git', '-c', 'user.email=test@example.com', '-c', 'user.name=test', 'commit', '-q', '-m', 'delivery'], $repository)->mustRun();
    }

    // The nominal reading, and the cheap one: what was recorded here is what the bundles still ship, so no history is walked at all and the warning this site gets on every update has nothing behind it
    public function testAFileTheScaffoldNeverMovedOnHasNothingToCarryOver(): void
    {
        $this->addScaffoldFile('src/Foo.php', 'delivered');
        $this->recordAsDelivered('src/Foo.php', 'delivered');
        $this->write($this->projectDir . '/src/Foo.php', 'mine');

        $result = $this->differ()->diff();

        $this->assertCount(1, $result['files']);
        $this->assertSame('src/Foo.php', $result['files'][0]['file']);
        $this->assertSame('the version recorded here', $result['files'][0]['base']);
        $this->assertSame('', $result['files'][0]['upstream']);
        $this->assertNull($result['files'][0]['fallback']);
    }

    // The version delivered is not the current one, and the site's own history still holds it - a customization committed after the delivery it came with
    public function testTheDeliveredVersionIsLookedUpInTheSiteHistoryFirst(): void
    {
        $this->addScaffoldFile('src/Foo.php', "delivered\nadded upstream\n");
        $this->commit($this->projectDir, 'src/Foo.php', "delivered\n");
        $this->recordAsDelivered('src/Foo.php', "delivered\n");
        file_put_contents($this->projectDir . '/src/Foo.php', 'mine');

        $result = $this->differ()->diff();

        $this->assertStringStartsWith('this site (', (string) $result['files'][0]['base']);
    }

    // The reading worth acting on: what the bundle added since this site's version, and only that - what the site itself wrote is its own business
    public function testAScaffoldThatMovedOnIsReportedAsTheDiffFromTheDeliveredVersion(): void
    {
        $this->addScaffoldFile('src/Foo.php', "delivered\nadded upstream\n");
        $this->commit($this->projectDir, 'src/Foo.php', "delivered\n");
        $this->recordAsDelivered('src/Foo.php', "delivered\n");
        file_put_contents($this->projectDir . '/src/Foo.php', "mine\n");

        $result = $this->differ()->diff();

        $this->assertStringContainsString('+added upstream', (string) $result['files'][0]['upstream']);
        $this->assertStringNotContainsString('mine', (string) $result['files'][0]['upstream']);
    }

    // A hunk carried over by hand must stop coming back, the same warning surviving the answer being what turns this into noise again - the line is looked for wherever it landed, a customized file rarely having room for it at the same place
    public function testAnAdditionAlreadyCarriedOverByHandIsNoLongerReported(): void
    {
        $this->addScaffoldFile('src/Foo.php', "delivered\nadded upstream\nadded too\n");
        $this->commit($this->projectDir, 'src/Foo.php', "delivered\n");
        $this->recordAsDelivered('src/Foo.php', "delivered\n");
        file_put_contents($this->projectDir . '/src/Foo.php', "added upstream\nmine\nadded too\n");

        $result = $this->differ()->diff();

        $this->assertSame('', $result['files'][0]['upstream']);
    }

    // Only what is answered drops out: a file holding one of the two additions is still missing the other
    public function testWhatIsStillMissingIsReportedAlongsideWhatWasCarriedOver(): void
    {
        // Ten lines apart, git's three lines of context on either side being what separates two additions into two hunks
        $filler = implode("\n", array_fill(0, 10, 'unchanged')) . "\n";
        $this->addScaffoldFile('src/Foo.php', "delivered\nadded upstream\n" . $filler . "added too\n");
        $this->commit($this->projectDir, 'src/Foo.php', "delivered\n" . $filler);
        $this->recordAsDelivered('src/Foo.php', "delivered\n" . $filler);
        file_put_contents($this->projectDir . '/src/Foo.php', "added upstream\nmine\n" . $filler);

        $result = $this->differ()->diff();

        $this->assertStringContainsString('+added too', (string) $result['files'][0]['upstream']);
        $this->assertStringNotContainsString('+added upstream', (string) $result['files'][0]['upstream']);
    }

    // The site committed the delivery and its own edit in one go, which is what an update script does - the version delivered was never a commit here, and only the bundle's own history still holds it
    public function testTheDeliveredVersionIsLookedUpInTheBundleClonesWhenTheSiteHistoryLacksIt(): void
    {
        $this->addScaffoldFile('src/Foo.php', "delivered\nadded upstream\n");
        $this->commit($this->projectDir, 'src/Foo.php', "mine\n");
        $this->commit($this->clonesDir . '/CoreBundle', 'scaffold/src/Foo.php', "delivered\n");
        $this->recordAsDelivered('src/Foo.php', "delivered\n");

        $result = $this->differ()->diff([], [$this->clonesDir]);

        $this->assertStringStartsWith('CoreBundle (', (string) $result['files'][0]['base']);
        $this->assertStringContainsString('+added upstream', (string) $result['files'][0]['upstream']);
    }

    // No history holds it: the plain local-vs-scaffold diff is still better than the warning alone, and saying so is what stops it from being read as "nothing to carry over"
    public function testAnUnrecoverableBaseFallsBackOnThePlainDiff(): void
    {
        $this->addScaffoldFile('src/Foo.php', "delivered\n");
        $this->write($this->projectDir . '/src/Foo.php', "mine\n");
        $this->recordAsDelivered('src/Foo.php', "delivered elsewhere\n");

        $result = $this->differ()->diff();

        $this->assertNull($result['files'][0]['base']);
        $this->assertNull($result['files'][0]['upstream']);
        $this->assertStringContainsString('+mine', (string) $result['files'][0]['fallback']);
    }

    // The way out for a report the site has read and decided against: the current scaffold becomes the base, so the same offer stops coming back - and the file itself is left strictly alone
    public function testAcknowledgingMovesTheRecordedBaseForwardWithoutTouchingTheFile(): void
    {
        $this->addScaffoldFile('src/Foo.php', "delivered\nadded upstream\n");
        $this->commit($this->projectDir, 'src/Foo.php', "delivered\n");
        $this->recordAsDelivered('src/Foo.php', "delivered\n");
        file_put_contents($this->projectDir . '/src/Foo.php', "mine\n");
        $differ = $this->differ();

        $acknowledged = $differ->acknowledge();

        $this->assertSame(['src/Foo.php'], $acknowledged['files']);
        $this->assertSame("mine\n", file_get_contents($this->projectDir . '/src/Foo.php'));
        $this->assertSame(hash('sha256', "delivered\nadded upstream\n"), json_decode((string) file_get_contents($this->projectDir . '/.c975l-scaffold.json'), true)['src/Foo.php']);
        $this->assertSame('', $differ->diff()['files'][0]['upstream']);
    }

    // What the bundle changes after an acknowledgement is a new offer, and is reported like any other
    public function testWhatTheScaffoldGainsAfterAnAcknowledgementIsReportedAgain(): void
    {
        $this->addScaffoldFile('src/Foo.php', "delivered\nadded upstream\n");
        $this->recordAsDelivered('src/Foo.php', "delivered\n");
        $this->write($this->projectDir . '/src/Foo.php', "mine\n");
        $this->differ()->acknowledge();

        $this->addScaffoldFile('src/Foo.php', "delivered\nadded upstream\nadded later\n");
        $this->commit($this->clonesDir . '/CoreBundle', 'scaffold/src/Foo.php', "delivered\nadded upstream\n");

        $result = $this->differ()->diff([], [$this->clonesDir]);

        $this->assertStringContainsString('+added later', (string) $result['files'][0]['upstream']);
    }

    // A path naming nothing must not read as a site with nothing to report, same as in "c975l:scaffold:install"
    public function testAPathMatchingNoScaffoldFileIsReportedBack(): void
    {
        $this->addScaffoldFile('src/Foo.php', 'delivered');

        $result = $this->differ()->diff(['src/Nawak']);

        $this->assertSame([], $result['files']);
        $this->assertSame(['src/Nawak'], $result['unmatched']);
    }

    // A template's own "<twig:…>" is a formatter tag to the console that displays this, and an unknown one throws
    public function testTheDiffComesBackEscapedForTheConsoleFormatter(): void
    {
        $this->addScaffoldFile('templates/foo.html.twig', "<twig:Nawak/>\n");
        $this->write($this->projectDir . '/templates/foo.html.twig', "mine\n");
        $this->recordAsDelivered('templates/foo.html.twig', "unrecoverable\n");

        $result = $this->differ()->diff();

        $this->assertStringContainsString('\\<twig:Nawak/\\>', (string) $result['files'][0]['fallback']);
    }
}
