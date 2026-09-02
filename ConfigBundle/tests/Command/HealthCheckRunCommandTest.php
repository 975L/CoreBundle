<?php

/*
 * (c) 2026: 975L <contact@example.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\HealthCheckRunCommand;
use c975L\ConfigBundle\Management\HealthCheckRetentionPurger;
use c975L\ConfigBundle\Management\HealthCheckRunner;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Routing\RequestContext;

class HealthCheckRunCommandTest extends TestCase
{
    public function testExecuteRunsEveryProviderWithoutTheKindOption(): void
    {
        $healthCheckRunner = $this->createMock(HealthCheckRunner::class);
        $healthCheckRunner->expects($this->once())->method('run')->with([])->willReturn(['pagespeed' => 3]);

        $tester = new CommandTester($this->createCommand($healthCheckRunner));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('pagespeed: 3 result(s) recorded', $tester->getDisplay());
    }

    public function testExecutePassesTheKindOptionThrough(): void
    {
        $healthCheckRunner = $this->createMock(HealthCheckRunner::class);
        $healthCheckRunner->method('getKinds')->willReturn(['wave']);
        $healthCheckRunner->expects($this->once())->method('run')->with(['wave'], null)->willReturn(['wave' => 1]);

        $tester = new CommandTester($this->createCommand($healthCheckRunner));
        $tester->execute(['--kind' => ['wave']]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteWarnsWhenNoProviderRan(): void
    {
        $healthCheckRunner = $this->createStub(HealthCheckRunner::class);
        $healthCheckRunner->method('run')->willReturn([]);

        $tester = new CommandTester($this->createCommand($healthCheckRunner));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('No HealthCheckProvider registered', $tester->getDisplay());
    }

    // What the scheduler asks for, so a cron entry never names kinds
    public function testExecutePassesTheFrequencyOptionThrough(): void
    {
        $healthCheckRunner = $this->createMock(HealthCheckRunner::class);
        $healthCheckRunner->expects($this->once())->method('run')->with([], 'monthly')->willReturn(['urls-gallery' => 12]);

        $tester = new CommandTester($this->createCommand($healthCheckRunner));
        $tester->execute(['--frequency' => 'monthly']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    // A typo in a cron entry has to be loud: silently running nothing is the very failure this option replaces
    public function testExecuteRejectsAnUnknownFrequency(): void
    {
        $healthCheckRunner = $this->createMock(HealthCheckRunner::class);
        $healthCheckRunner->expects($this->never())->method('run');

        $tester = new CommandTester($this->createCommand($healthCheckRunner));
        $tester->execute(['--frequency' => 'daily']);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown frequency "daily"', $tester->getDisplay());
    }

    // A kind no installed bundle provides used to be skipped without a word
    public function testExecuteWarnsAboutAKindNoProviderDeclares(): void
    {
        $healthCheckRunner = $this->createStub(HealthCheckRunner::class);
        $healthCheckRunner->method('getKinds')->willReturn(['pagespeed']);
        $healthCheckRunner->method('run')->willReturn(['pagespeed' => 1]);

        $tester = new CommandTester($this->createCommand($healthCheckRunner));
        $tester->execute(['--kind' => ['pagespeed', 'urls-gallery']]);

        $display = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('urls-gallery', $display);
        $this->assertStringContainsString('Available: pagespeed', $display);
    }

    // Nothing ran because the filter matched nothing, which is not the same as a site with no provider at all
    public function testExecuteTellsAFilterMatchingNothingFromAnEmptySite(): void
    {
        $healthCheckRunner = $this->createStub(HealthCheckRunner::class);
        $healthCheckRunner->method('getKinds')->willReturn(['pagespeed']);
        $healthCheckRunner->method('run')->willReturn([]);

        $tester = new CommandTester($this->createCommand($healthCheckRunner));
        $tester->execute(['--frequency' => 'monthly']);

        $this->assertStringContainsString('No provider matches the given filter', $tester->getDisplay());
    }

    // The install with no provider at all is precisely the one still collecting a backup row every six hours: its history has to be purged too, which a purge wired after the "nothing to run" return would never do
    public function testExecutePurgesEvenWhenNoProviderRan(): void
    {
        $healthCheckRunner = $this->createStub(HealthCheckRunner::class);
        $healthCheckRunner->method('run')->willReturn([]);

        $purger = $this->createMock(HealthCheckRetentionPurger::class);
        $purger->expects($this->once())->method('purge')->willReturn(120);

        $tester = new CommandTester($this->createCommand($healthCheckRunner, $purger));
        $tester->execute([]);

        $this->assertStringContainsString('120 result(s) past the retention window purged', $tester->getDisplay());
    }

    // A run that purged nothing says nothing: the line is news, not a heartbeat
    public function testExecuteStaysSilentWhenNothingWasPurged(): void
    {
        $healthCheckRunner = $this->createStub(HealthCheckRunner::class);
        $healthCheckRunner->method('run')->willReturn(['pagespeed' => 1]);

        $tester = new CommandTester($this->createCommand($healthCheckRunner));
        $tester->execute([]);

        $this->assertStringNotContainsString('retention window', $tester->getDisplay());
    }

    // Every url this run records is generated without a request, so the context is what decides whether they point at the site or at localhost
    public function testExecuteBuildsUrlsOnTheConfiguredSiteUrl(): void
    {
        $healthCheckRunner = $this->createStub(HealthCheckRunner::class);
        $healthCheckRunner->method('run')->willReturn(['pagespeed' => 1]);

        $requestContext = new RequestContext();
        $tester = new CommandTester($this->createCommand($healthCheckRunner, null, 'https://example.com', $requestContext));
        $tester->execute([]);

        $this->assertSame('https', $requestContext->getScheme());
        $this->assertSame('example.com', $requestContext->getHost());
    }

    // A site url carrying a port and a path, as a local install has: dropping either points the url nowhere
    public function testExecuteKeepsThePortAndThePathOfTheSiteUrl(): void
    {
        $healthCheckRunner = $this->createStub(HealthCheckRunner::class);
        $healthCheckRunner->method('run')->willReturn(['pagespeed' => 1]);

        $requestContext = new RequestContext();
        $tester = new CommandTester($this->createCommand($healthCheckRunner, null, 'http://localhost:8000/site/', $requestContext));
        $tester->execute([]);

        $this->assertSame(8000, $requestContext->getHttpPort());
        $this->assertSame('/site', $requestContext->getBaseUrl());
    }

    // An unconfigured site url leaves the context as it was, rather than blanking the host it already holds
    public function testExecuteLeavesTheContextAloneWithoutASiteUrl(): void
    {
        $healthCheckRunner = $this->createStub(HealthCheckRunner::class);
        $healthCheckRunner->method('run')->willReturn(['pagespeed' => 1]);

        $requestContext = new RequestContext();
        $requestContext->setHost('kept.example');

        $tester = new CommandTester($this->createCommand($healthCheckRunner, null, null, $requestContext));
        $tester->execute([]);

        $this->assertSame('kept.example', $requestContext->getHost());
    }

    private function createCommand(HealthCheckRunner $healthCheckRunner, ?HealthCheckRetentionPurger $purger = null, ?string $siteUrl = null, ?RequestContext $requestContext = null): HealthCheckRunCommand
    {
        if (null === $purger) {
            $purger = $this->createStub(HealthCheckRetentionPurger::class);
            $purger->method('purge')->willReturn(0);
        }

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new HealthCheckRunCommand($healthCheckRunner, $purger, $configService, $requestContext ?? new RequestContext());
    }
}
