<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\EventSubscriber;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

// Records every successful login, which restarts the inactivity clock of c975l:config:users-cleanup and withdraws a notice already sent
class LastLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        // A site whose User doesn't track its inactivity yet, or a user coming from another provider (in-memory admin...)
        $user = $event->getUser();
        if (!$user instanceof InactivityAwareInterface) {
            return;
        }

        $user
            ->setLastLogin(new \DateTime())
            ->setInactivityNoticeSentAt(null);
        $this->entityManager->flush();
    }
}
