<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\CapabilitiesStatusProvider;
use c975L\ConfigBundle\Service\EnvironmentProbe;
use PHPUnit\Framework\TestCase;

class CapabilitiesStatusProviderTest extends TestCase
{
    public function testGetStatusKey(): void
    {
        $this->assertSame('capabilities', new CapabilitiesStatusProvider(new EnvironmentProbe())->getStatusKey());
    }

    // A reading is only ever true of the SAPI it came from, php-cli being configured separately from php-fpm: without it, two sites reporting different values cannot be told apart from one site read twice
    public function testTheReportIsAttributableToTheSapiItWasReadThrough(): void
    {
        $data = new CapabilitiesStatusProvider(new EnvironmentProbe())->getStatusData();

        $this->assertSame(\PHP_SAPI, $data['sapi']);
        $this->assertIsBool($data['exec']);
        $this->assertIsArray($data['directives']);
    }

    // The generic floor only: what a given bundle needs on top belongs to that bundle's own provider, ConfigBundle having no way to know what is installed alongside it
    public function testItReportsNoBundleSpecificRequirement(): void
    {
        $data = new CapabilitiesStatusProvider(new EnvironmentProbe())->getStatusData();

        $this->assertSame(['sapi', 'exec', 'directives'], array_keys($data));
    }
}
