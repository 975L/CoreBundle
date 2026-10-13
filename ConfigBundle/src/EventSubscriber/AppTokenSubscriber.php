<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\EventSubscriber;

use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use c975L\ConfigBundle\Repository\AppTokenRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// An account anonymized keeps its row, so the "CASCADE" of AppToken never fires: its apps are signed out here instead, an erased account answering to no device
class AppTokenSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AppTokenRepository $appTokenRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [UserAnonymizedEvent::class => 'onUserAnonymized'];
    }

    public function onUserAnonymized(UserAnonymizedEvent $event): void
    {
        $this->appTokenRepository->deleteOfUser($event->user);
    }
}
