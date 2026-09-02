<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\EventSubscriber;

use c975L\ConfigBundle\EventSubscriber\NotFoundSubscriber;
use c975L\ConfigBundle\Repository\NotFoundRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class NotFoundSubscriberTest extends TestCase
{
    private const string SEEN_AT = '2026-08-23 10:00:00';

    private function createEvent(
        string $url = 'https://example.com/histoire/disparue',
        ?string $referer = 'https://example.com/histoires',
        \Throwable $throwable = new NotFoundHttpException(),
        string $method = 'GET',
        bool $isMainRequest = true,
    ): ExceptionEvent {
        $server = null === $referer ? [] : ['HTTP_REFERER' => $referer];
        $kernel = $this->createStub(HttpKernelInterface::class);
        $requestType = $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST;

        return new ExceptionEvent($kernel, Request::create($url, $method, [], [], [], $server), $requestType, $throwable);
    }

    /**
     * @return NotFoundRepository&MockObject
     */
    private function createRepository(): NotFoundRepository
    {
        return $this->createMock(NotFoundRepository::class);
    }

    private function createSubscriber(NotFoundRepository $repository): NotFoundSubscriber
    {
        return new NotFoundSubscriber($repository, new MockClock(self::SEEN_AT));
    }

    // Runs on its own, well before the listener turning the exception into the response (priority -128)
    public function testGetSubscribedEventsRunsBeforeTheErrorListener(): void
    {
        $this->assertSame([KernelEvents::EXCEPTION => ['onKernelException', 0]], NotFoundSubscriber::getSubscribedEvents());
    }

    // A link on one of our own pages: the path, the page carrying it, and the flag saying it is ours to fix
    public function testRecordsAnInternalBrokenLink(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->once())
            ->method('record')
            ->with('/histoire/disparue', 'https://example.com/histoires', true, new \DateTimeImmutable(self::SEEN_AT))
        ;

        $this->createSubscriber($repository)->onKernelException($this->createEvent());
    }

    // The same url linked from elsewhere is worth a redirect, not an alert - hence the flag rather than a second table
    public function testRecordsAnExternalBrokenLinkAsNotInternal(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->once())
            ->method('record')
            ->with('/histoire/disparue', 'https://elsewhere.example/blog', false, new \DateTimeImmutable(self::SEEN_AT))
        ;

        $this->createSubscriber($repository)->onKernelException($this->createEvent(referer: 'https://elsewhere.example/blog'));
    }

    // The whole filter: a scanner walking for an admin panel sends no referer, and that is what keeps the table down to links that exist somewhere
    public function testRecordsNothingWithoutAReferer(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->never())->method('record');

        $this->createSubscriber($repository)->onKernelException($this->createEvent(url: 'https://example.com/wp-admin', referer: null));
    }

    // A referer that is not a url at all is a forged header, not a page anyone could open
    public function testRecordsNothingForARefererWithoutAHost(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->never())->method('record');

        $this->createSubscriber($repository)->onKernelException($this->createEvent(referer: 'not-a-url'));
    }

    // A header is whatever its sender wrote: this one carries our own host, and would otherwise be filed as one of our broken links and listed as a link to click on
    public function testRecordsNothingForARefererThatIsNotAWebUrl(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->never())->method('record');

        $this->createSubscriber($repository)->onKernelException($this->createEvent(referer: 'javascript://example.com/%0aalert(1)'));
    }

    // A 410 is a url someone decided to remove (see RedirectSubscriber), which is an answer, not a broken link
    public function testRecordsNothingForAGoneUrl(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->never())->method('record');

        $this->createSubscriber($repository)->onKernelException($this->createEvent(throwable: new GoneHttpException()));
    }

    // Only what a browser can be sent to by a link: a POST landing on a missing route is a form or an API call, not a url to redirect
    public function testRecordsNothingForANonGetRequest(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->never())->method('record');

        $this->createSubscriber($repository)->onKernelException($this->createEvent(method: 'POST'));
    }

    // The same paths a redirect refuses to be written on: a missing asset is a deployment matter
    public function testRecordsNothingForAStaticAsset(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->never())->method('record');

        $this->createSubscriber($repository)->onKernelException($this->createEvent(url: 'https://example.com/assets/app-123456.css'));
    }

    // Skipped rather than truncated: half a path is a row nobody could act on
    public function testRecordsNothingForAPathLongerThanTheColumn(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->never())->method('record');

        $this->createSubscriber($repository)->onKernelException($this->createEvent(url: 'https://example.com/' . str_repeat('a', 300)));
    }

    public function testRecordsNothingForASubRequest(): void
    {
        $repository = $this->createRepository();
        $repository->expects($this->never())->method('record');

        $this->createSubscriber($repository)->onKernelException($this->createEvent(isMainRequest: false));
    }

    // Taking a note must never turn the 404 page into a 500 - a site whose migration has not run yet has no table to write to
    public function testAFailureToRecordNeverEscapes(): void
    {
        $repository = $this->createStub(NotFoundRepository::class);
        $repository->method('record')->willThrowException(new \RuntimeException('no such table'));

        $this->expectNotToPerformAssertions();

        $this->createSubscriber($repository)->onKernelException($this->createEvent());
    }
}
