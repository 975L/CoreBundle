<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Listener;

use c975L\ConfigBundle\Listener\TimezoneListener;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Twig\Environment;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;

// The hour every template shows comes from "site-timezone" and not from the machine, PHP falling back on UTC as long as date.timezone is left out of its php.ini
class TimezoneListenerTest extends TestCase
{
    private function createTwig(): Environment
    {
        return new Environment(new ArrayLoader());
    }

    private function createListener(Environment $twig, mixed $value): TimezoneListener
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug) => 'site-timezone' === $slug ? $value : null);

        return new TimezoneListener($configService, $twig);
    }

    public function testTheConfiguredTimezoneIsTheOneTemplatesRead(): void
    {
        $twig = $this->createTwig();
        $this->createListener($twig, 'Europe/Paris')->apply();

        $this->assertSame('Europe/Paris', $twig->getExtension(CoreExtension::class)->getTimezone()->getName());
    }

    // A site restored from an older base can hold anything: a wrong timezone leaves the dates where they were rather than stopping the page
    public function testAnUnknownIdentifierIsLeftAlone(): void
    {
        $twig = $this->createTwig();
        $twig->getExtension(CoreExtension::class)->setTimezone('UTC');

        $this->createListener($twig, 'Europe/Thryndor')->apply();

        $this->assertSame('UTC', $twig->getExtension(CoreExtension::class)->getTimezone()->getName());
    }

    // A site that never set the entry - one upgrading to this version - keeps reading its dates as it did
    public function testNoConfiguredValueChangesNothing(): void
    {
        $twig = $this->createTwig();
        $twig->getExtension(CoreExtension::class)->setTimezone('UTC');

        $this->createListener($twig, null)->apply();

        $this->assertSame('UTC', $twig->getExtension(CoreExtension::class)->getTimezone()->getName());
    }

    private function createEvent(string $name): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent(new Command($name), new ArrayInput([]), new NullOutput());
    }

    public function testAConsoleCommandReadsTheConfiguredTimezone(): void
    {
        $twig = $this->createTwig();
        $this->createListener($twig, 'Europe/Paris')->applyOnConsole($this->createEvent('c975l:sitemaps:create'));

        $this->assertSame('Europe/Paris', $twig->getExtension(CoreExtension::class)->getTimezone()->getName());
    }

    // On an empty cache the read goes through Doctrine, and its metadata warmer throws once any metadata is loaded: the configuration is not read at all
    public function testCacheWarmupNeverReadsTheConfiguration(): void
    {
        $configService = $this->createMock(ConfigServiceInterface::class);
        $configService->expects($this->never())->method('get');

        new TimezoneListener($configService, $this->createTwig())->applyOnConsole($this->createEvent('cache:warmup'));
    }
}
