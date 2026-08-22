<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

// A started session costs a connection to its store on every single request, so nothing rendered for an anonymous visitor may start one - see the flash guards in SiteBundle's and UiBundle's layouts, the regression this covers
class AnonymousSessionTest extends WebTestCase
{
    public function testAnonymousRequestStartsNoSession(): void
    {
        $client = static::createClient();

        // Read on kernel.response rather than after the request: the session listener saves and closes the session at priority -1000, and a closed session reports itself as not started whatever happened during the request
        $started = null;
        static::getContainer()->get('event_dispatcher')->addListener(
            KernelEvents::RESPONSE,
            static function (ResponseEvent $event) use (&$started): void {
                $request = $event->getRequest();
                $started = $request->hasSession() && $request->getSession()->isStarted();
            }
        );

        $client->request('GET', '/');

        $this->assertFalse($started, 'Une requête anonyme a démarré une session : chaque visiteur ouvre donc une connexion inutile à son stockage');
    }
}
