<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Controller\ReviewController;
use c975L\UiBundle\Model\CollectionItem;
use c975L\UiBundle\Registry\FavoriteItemRegistry;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\RateLimiterGuard;
use c975L\UiBundle\Service\ReviewService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

// The two gates the public route closes before anything else happens - what is rendered past them needs a Twig runtime, and is checked by the templates' own tests
class ReviewControllerTest extends TestCase
{
    // A site that collects no reviews serves no page to write one on, rather than a form whose submissions nobody would ever read
    public function testTheRouteIsNotServedWhileTheFeatureIsOff(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(enabled: false)->new('book', 12, new Request());
    }

    // An id nobody claims, or one whose owner is not published: there is nothing to review, and nothing to name the page after
    public function testAnOwnerNobodyResolvesIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(resolved: [])->new('book', 12, new Request());
    }

    // The bot check must not even be reached on a page that is not served: it would start a timer in a session for a form nobody gets
    public function testTheBotTimerIsNotStartedOnARouteThatIsNotServed(): void
    {
        $botProtection = $this->createMock(FormBotProtection::class);
        $botProtection->expects($this->never())->method('startTimer');

        try {
            $this->controller(enabled: false, botProtection: $botProtection)->new('book', 12, new Request());
        } catch (NotFoundHttpException) {
            // The gate is what this checks; the expectation above is what witnesses it
        }
    }

    /**
     * @param list<array{ownerType: string, ownerId: int, item: CollectionItem}>|null $resolved
     */
    private function controller(
        bool $enabled = true,
        ?array $resolved = null,
        ?FormBotProtection $botProtection = null,
    ): ReviewController {
        $reviewService = $this->createStub(ReviewService::class);
        $reviewService->method('isEnabled')->willReturn($enabled);

        $favoriteItemRegistry = $this->createStub(FavoriteItemRegistry::class);
        $favoriteItemRegistry->method('resolve')->willReturn($resolved ?? [
            ['ownerType' => 'book', 'ownerId' => 12, 'item' => new CollectionItem(title: 'La Princesse et les Monstres')],
        ]);

        return new ReviewController(
            $reviewService,
            $favoriteItemRegistry,
            $botProtection ?? $this->createStub(FormBotProtection::class),
            $this->createStub(RateLimiterGuard::class),
            $this->createStub(TranslatorInterface::class),
        );
    }
}
