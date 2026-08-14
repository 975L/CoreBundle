<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\EventSubscriber;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\EventSubscriber\RedirectSubscriber;
use c975L\ConfigBundle\Repository\RedirectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class RedirectSubscriberTest extends TestCase
{
    private function createEvent(string $path, bool $isMainRequest = true): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $requestType = $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST;

        return new RequestEvent($kernel, Request::create($path), $requestType);
    }

    // The repository hands back every candidate for the path in one query (the exact row plus every prefix one) and the subscriber decides which of them applies
    private function createSubscriber(Redirect ...$candidates): RedirectSubscriber
    {
        $repository = $this->createStub(RedirectRepository::class);
        $repository->method('findCandidatesForPath')->willReturn($candidates);

        return new RedirectSubscriber($repository);
    }

    // Runs before RouterListener (priority 33 > 32) so a redirect can short-circuit routing entirely
    public function testGetSubscribedEventsRunsBeforeRouterListener(): void
    {
        $this->assertSame([KernelEvents::REQUEST => ['onKernelRequest', 33]], RedirectSubscriber::getSubscribedEvents());
    }

    // A path matching a stored redirect gets a response set, with the status matching its permanent flag
    public function testOnKernelRequestSetsPermanentRedirectResponse(): void
    {
        $subscriber = $this->createSubscriber(new Redirect()->setFromPath('/old')->setToUrl('/new')->setPermanent(true));
        $event = $this->createEvent('/old');

        $subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/new', $response->getTargetUrl());
        $this->assertSame(301, $response->getStatusCode());
    }

    // A non-permanent redirect yields a 302 instead of a 301
    public function testOnKernelRequestSetsTemporaryRedirectResponse(): void
    {
        $subscriber = $this->createSubscriber(new Redirect()->setFromPath('/old')->setToUrl('/new')->setPermanent(false));
        $event = $this->createEvent('/old');

        $subscriber->onKernelRequest($event);

        $this->assertSame(302, $event->getResponse()->getStatusCode());
    }

    // No matching redirect: no response is set, request proceeds normally
    public function testOnKernelRequestDoesNothingWhenNoRedirectMatches(): void
    {
        $subscriber = $this->createSubscriber();
        $event = $this->createEvent('/unknown');

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    // A non-gone row whose destination is empty is unusable, and this subscriber runs before the router: building a RedirectResponse out of it would throw a TypeError and 500 the path outright
    public function testOnKernelRequestIgnoresARowWithoutADestination(): void
    {
        $subscriber = $this->createSubscriber(new Redirect()->setFromPath('/old'));
        $event = $this->createEvent('/old');

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    // A url declared gone answers 410, not a redirect - there is nothing to redirect to
    public function testOnKernelRequestThrowsGoneForAGoneRedirect(): void
    {
        $subscriber = $this->createSubscriber(new Redirect()->setFromPath('/removed')->setGone(true));
        $event = $this->createEvent('/removed');

        $this->expectException(GoneHttpException::class);
        $subscriber->onKernelRequest($event);
    }

    // A single "/apidoc/*" row covers every url below it, however deep
    public function testOnKernelRequestMatchesAPrefixRow(): void
    {
        $subscriber = $this->createSubscriber(new Redirect()->setFromPath('/apidoc/*')->setGone(true));
        $event = $this->createEvent('/apidoc/c975L/ConfigBundle/Form.html');

        $this->expectException(GoneHttpException::class);
        $subscriber->onKernelRequest($event);
    }

    // "/fr/*" covers the bare "/fr/" too, its prefix being "/fr/" itself
    public function testOnKernelRequestMatchesAPrefixRowOnTheBarePrefix(): void
    {
        $subscriber = $this->createSubscriber(new Redirect()->setFromPath('/fr/*')->setGone(true));
        $event = $this->createEvent('/fr/');

        $this->expectException(GoneHttpException::class);
        $subscriber->onKernelRequest($event);
    }

    // A prefix only matches what is actually below it
    public function testOnKernelRequestIgnoresANonMatchingPrefixRow(): void
    {
        $subscriber = $this->createSubscriber(new Redirect()->setFromPath('/apidoc/*')->setGone(true));
        $event = $this->createEvent('/pages/blocks');

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    // An exact row wins over a prefix covering it, so one url of a removed tree can keep an answer of its own
    public function testOnKernelRequestPrefersAnExactRowOverAPrefixCoveringIt(): void
    {
        $subscriber = $this->createSubscriber(
            new Redirect()->setFromPath('/apidoc/*')->setGone(true),
            new Redirect()->setFromPath('/apidoc/kept.html')->setToUrl('/pages/bundles')->setPermanent(true),
        );
        $event = $this->createEvent('/apidoc/kept.html');

        $subscriber->onKernelRequest($event);

        $this->assertSame('/pages/bundles', $event->getResponse()->getTargetUrl());
    }

    // Between two prefixes both covering the path, the most specific one applies
    public function testOnKernelRequestPrefersTheLongestMatchingPrefix(): void
    {
        $subscriber = $this->createSubscriber(
            new Redirect()->setFromPath('/apidoc/*')->setGone(true),
            new Redirect()->setFromPath('/apidoc/c975L/*')->setToUrl('/pages/bundles')->setPermanent(true),
        );
        $event = $this->createEvent('/apidoc/c975L/ConfigBundle/Form.html');

        $subscriber->onKernelRequest($event);

        $this->assertSame('/pages/bundles', $event->getResponse()->getTargetUrl());
    }

    // A "*" on both sides renames a whole url tree: what follows the prefix is carried over to the destination
    public function testOnKernelRequestCarriesTheTailOverToAStarredDestination(): void
    {
        $subscriber = $this->createSubscriber(new Redirect()->setFromPath('/character/*')->setToUrl('/personnages/*')->setPermanent(true));
        $event = $this->createEvent('/character/tuor');

        $subscriber->onKernelRequest($event);

        $this->assertSame('/personnages/tuor', $event->getResponse()->getTargetUrl());
    }

    // The tail is whatever follows the prefix, however deep
    public function testOnKernelRequestCarriesADeepTailOverToAStarredDestination(): void
    {
        $subscriber = $this->createSubscriber(new Redirect()->setFromPath('/old/*')->setToUrl('https://example.org/new/*')->setPermanent(true));
        $event = $this->createEvent('/old/a/b/c.html');

        $subscriber->onKernelRequest($event);

        $this->assertSame('https://example.org/new/a/b/c.html', $event->getResponse()->getTargetUrl());
    }

    // A destination without a "*" keeps folding the whole tree onto one url, which is the other half of what a prefix row is for
    public function testOnKernelRequestFoldsAPrefixRowOntoADestinationWithoutAStar(): void
    {
        $subscriber = $this->createSubscriber(new Redirect()->setFromPath('/character/*')->setToUrl('/personnages')->setPermanent(true));
        $event = $this->createEvent('/character/tuor');

        $subscriber->onKernelRequest($event);

        $this->assertSame('/personnages', $event->getResponse()->getTargetUrl());
    }

    // A "*" on the destination of an exact row means nothing and is left alone: the pairing only applies to a prefix row
    public function testOnKernelRequestLeavesAStarredDestinationOfAnExactRowAlone(): void
    {
        $subscriber = $this->createSubscriber(new Redirect()->setFromPath('/old')->setToUrl('/new/*')->setPermanent(true));
        $event = $this->createEvent('/old');

        $subscriber->onKernelRequest($event);

        $this->assertSame('/new/*', $event->getResponse()->getTargetUrl());
    }

    // Requesting a prefix row on its literal path lands on the destination it states, which is what lets such a row be checked as written (see the site-side ContentAccessTest, which walks every stored row)
    public function testOnKernelRequestAnswersAStarredRowRequestedAsWritten(): void
    {
        $subscriber = $this->createSubscriber(new Redirect()->setFromPath('/character/*')->setToUrl('/personnages/*')->setPermanent(true));
        $event = $this->createEvent('/character/*');

        $subscriber->onKernelRequest($event);

        $this->assertSame('/personnages/*', $event->getResponse()->getTargetUrl());
    }

    // The homepage ('/') is never looked up, avoiding a pointless query on the hottest path
    public function testOnKernelRequestSkipsHomepage(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects($this->never())->method('findCandidatesForPath');
        $subscriber = new RedirectSubscriber($repository);
        $event = $this->createEvent('/');

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    // Sub-requests (e.g. ESI/fragments) are ignored entirely
    public function testOnKernelRequestIgnoresSubRequests(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects($this->never())->method('findCandidatesForPath');
        $subscriber = new RedirectSubscriber($repository);
        $event = $this->createEvent('/old', isMainRequest: false);

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }
}
