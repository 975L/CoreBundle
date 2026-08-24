<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Model\CollectionItem;
use c975L\UiBundle\Repository\ReviewRepository;
use c975L\UiBundle\Service\ReviewService;
use c975L\UiBundle\Service\ReviewTokenSigner;
use c975L\UiBundle\Twig\ReviewRuntime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;

// What a page asks for to draw the reviews of the thing it is about
class ReviewRuntimeTest extends TestCase
{
    // The very card the review wall draws its own from, so a book's reviews and the site's never drift apart
    public function testReviewsComeBackAsTheCollectionItemTheWallAlreadyDraws(): void
    {
        $items = $this->runtime(true, $this->review())->reviews('book', 12);

        $this->assertCount(1, $items);
        $this->assertInstanceOf(CollectionItem::class, $items[0]);
        $this->assertSame('Jean D.', $items[0]->title);
        $this->assertSame('Impeccable', $items[0]->description);
        $this->assertSame(4, $items[0]->data['rating']);
    }

    // A template asking for them on a site collecting none draws nothing at all, where a missing function would break the page it was added to
    public function testNothingComesBackWhileTheFeatureIsOff(): void
    {
        $this->assertSame([], $this->runtime(false, $this->review())->reviews('book', 12));
    }

    // The repository is not even reached: a site that collects no reviews runs no query to say so
    public function testTheRepositoryIsNotQueriedWhileTheFeatureIsOff(): void
    {
        $repository = $this->createMock(ReviewRepository::class);
        $repository->expects($this->never())->method('findForOwner');

        $reviewService = $this->createStub(ReviewService::class);
        $reviewService->method('isEnabled')->willReturn(false);

        $this->runtimeWith($repository, $reviewService)->reviews('book', 12);
    }

    // What a template reads to decide on its own whether to draw the section and its "leave a review" link
    public function testTheFeatureSwitchIsReadableOnItsOwn(): void
    {
        $this->assertTrue($this->runtime(true)->reviewsEnabled());
        $this->assertFalse($this->runtime(false)->reviewsEnabled());
    }

    // The url a visitor writes on names what is reviewed through a signed token, never through its id - which is the whole point of building it here rather than with a path() call
    public function testTheFormUrlCarriesASignedTokenAndNoId(): void
    {
        $url = $this->runtime(true)->url('book', 12);

        $this->assertStringStartsWith('/ui_review_new/', $url);
        $this->assertStringNotContainsString('12', $url);
        $this->assertSame(
            ['ownerType' => 'book', 'ownerId' => 12],
            $this->signer()->unsign(substr($url, \strlen('/ui_review_new/')))
        );
    }

    // Nothing in the section belongs to one visitor, so it is rendered once and kept - in the very cache the blocks of the page around it are held in
    public function testTheSectionIsRenderedThroughTheCacheItIsTaggedIn(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('get')->willReturn('<section class="reviews"></section>');

        $runtime = $this->runtimeWith(
            $this->createStub(ReviewRepository::class),
            $this->enabledService(true),
            $cache,
            new RequestStack([new Request()]),
        );

        $this->assertSame('<section class="reviews"></section>', (string) $runtime->section('book', 12));
    }

    // A site collecting no reviews draws no section at all, and nothing about it is stored - the sheets carry no condition of their own any more
    public function testNoSectionIsRenderedNorStoredWhileTheFeatureIsOff(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $runtime = $this->runtimeWith(
            $this->createStub(ReviewRepository::class),
            $this->enabledService(false),
            $cache,
            new RequestStack([new Request()]),
        );

        $this->assertSame('', (string) $runtime->section('book', 12));
    }

    // Rendered outside any http request - a console command, a messenger worker - the locale of that render being nobody's: rendered, never stored (same reading as the blocks' own cache)
    public function testTheSectionIsNotStoredOutsideAnyRequest(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('get');

        $runtime = $this->runtimeWith(
            $this->createStub(ReviewRepository::class),
            $this->enabledService(true),
            $cache,
            new RequestStack(),
        );

        $this->assertSame('', (string) $runtime->section('book', 12));
    }

    private function enabledService(bool $enabled): ReviewService
    {
        $reviewService = $this->createStub(ReviewService::class);
        $reviewService->method('isEnabled')->willReturn($enabled);

        return $reviewService;
    }

    private function review(): Review
    {
        return new Review()
            ->setAuthorName('Jean D.')
            ->setRating(4)
            ->setComment('Impeccable')
        ;
    }

    private function runtime(bool $enabled, Review ...$reviews): ReviewRuntime
    {
        $repository = $this->createStub(ReviewRepository::class);
        $repository->method('findForOwner')->willReturn($reviews);

        $reviewService = $this->createStub(ReviewService::class);
        $reviewService->method('isEnabled')->willReturn($enabled);

        return $this->runtimeWith($repository, $reviewService);
    }

    private function runtimeWith(
        ReviewRepository $repository,
        ReviewService $reviewService,
        ?TagAwareCacheInterface $cache = null,
        ?RequestStack $requestStack = null,
    ): ReviewRuntime {
        return new ReviewRuntime(
            $repository,
            $reviewService,
            $this->signer(),
            $this->urlGenerator(),
            $this->createStub(Environment::class),
            $cache ?? $this->createStub(TagAwareCacheInterface::class),
            $requestStack ?? new RequestStack(),
        );
    }

    private function signer(): ReviewTokenSigner
    {
        return new ReviewTokenSigner('a-secret');
    }

    // Answers with the route and the parameters it was given, so what the runtime signed is readable in the url it returns
    private function urlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []) => '/' . $route . '/' . ($parameters['token'] ?? '')
        );

        return $urlGenerator;
    }
}
