<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\EventSubscriber;

use c975L\ConfigBundle\Controller\OAuthLoginController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

// Clears the flag of a session opened through a provider on every login, so a form login in the same session gets its password change back. OAuthLoginController sets it again right after its own login, which dispatches this event first
class OAuthLoginFlagSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->hasPreviousSession()) {
            $request->getSession()->remove(OAuthLoginController::SESSION_OAUTH_LOGIN);
        }
    }
}
