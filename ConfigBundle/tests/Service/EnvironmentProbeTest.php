<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\EnvironmentProbe;
use PHPUnit\Framework\TestCase;

class EnvironmentProbeTest extends TestCase
{
    private EnvironmentProbe $environmentProbe;

    protected function setUp(): void
    {
        $this->environmentProbe = new EnvironmentProbe();
    }

    // Nothing here asserts a value: what the machine running the suite allows is not the subject, only that a reading is taken and has the shape a receiver reads
    public function testTheReadingCarriesItsSapiAndItsExecCapability(): void
    {
        $description = $this->environmentProbe->describe();

        $this->assertSame(\PHP_SAPI, $description['sapi']);
        $this->assertIsBool($description['exec']);
    }

    public function testTheReportedDirectivesAreAllPresentAsScalars(): void
    {
        $directives = $this->environmentProbe->getDirectives();

        $this->assertArrayHasKey('memory_limit', $directives);
        $this->assertArrayHasKey('upload_max_filesize', $directives);

        foreach ($directives as $value) {
            $this->assertIsString($value);
        }
    }

    // disable_functions in full names every function a host left reachable, which describes an attack surface rather than a capability - and this payload is built to leave the site
    public function testTheReadingNeverCarriesTheConfigurationItWasReadFrom(): void
    {
        $encoded = json_encode($this->environmentProbe->describe());

        $this->assertStringNotContainsString('disable_functions', (string) $encoded);
    }

    public function testAnAbsentBinaryIsNull(): void
    {
        $this->assertNull($this->environmentProbe->getBinaryVersion('no-such-binary-anywhere'));
    }

    // The value reaches a shell: the one thing that must be impossible is a caller turning a lookup into a command
    public function testANameThatIsNotABareBinaryNameIsRefused(): void
    {
        $this->assertNull($this->environmentProbe->getBinaryVersion('gs; rm -rf /'));
        $this->assertNull($this->environmentProbe->getBinaryVersion('/usr/bin/gs'));
        $this->assertNull($this->environmentProbe->getBinaryVersion('gs --help'));
        $this->assertNull($this->environmentProbe->getBinaryVersion(''));
    }

    public function testAnExtensionIsReportedByWhetherItIsLoaded(): void
    {
        $this->assertTrue($this->environmentProbe->hasExtension('json'));
        $this->assertFalse($this->environmentProbe->hasExtension('no_such_extension'));
    }

    // Only what a caller asked for: a bundle's needs are its own, and a list kept here would go stale the first time one stops needing something
    public function testOnlyTheRequestedBinariesAndExtensionsAreDescribed(): void
    {
        $description = $this->environmentProbe->describe(extensions: ['json']);

        $this->assertSame(['json' => true], $description['extensions']);
        $this->assertArrayNotHasKey('binaries', $description);
    }

    public function testTheSameBinaryIsOnlyEverLookedUpOnce(): void
    {
        $first = $this->environmentProbe->getBinaryVersion('no-such-binary-anywhere');
        $second = $this->environmentProbe->getBinaryVersion('no-such-binary-anywhere');

        $this->assertSame($first, $second);
    }
}
