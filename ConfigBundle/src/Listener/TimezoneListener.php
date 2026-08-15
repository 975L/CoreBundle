<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Listener;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Twig\Environment;
use Twig\Extension\CoreExtension;

// The hour every template shows, taken from "site-timezone" rather than from the machine: PHP falls back on UTC as long as date.timezone is left out of its php.ini, and a back-office two hours behind the clock is one whose dates stop being read. Only the reading is moved - PHP itself keeps writing in its own timezone, a date stored in base having no business depending on where the server sits
// On the console too, and not on requests alone: a command or a Messenger worker naming a file after the hour reads it here as well
#[AsEventListener(event: 'kernel.request', method: 'apply', priority: 7)]
#[AsEventListener(event: 'console.command', method: 'apply')]
class TimezoneListener
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly Environment $twig,
    ) {
    }

    public function apply(): void
    {
        $timezone = $this->configService->get('site-timezone');

        // An unknown identifier is left alone rather than thrown: the value is picked from a list in the back-office, but a site restored from an older base can hold anything, and a wrong timezone is no reason to answer nothing at all
        if (!\is_string($timezone) || !\in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            return;
        }

        $this->twig->getExtension(CoreExtension::class)->setTimezone($timezone);
    }
}
