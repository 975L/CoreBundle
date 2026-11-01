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
use c975L\UiBundle\Service\CurrentAccountAddress;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\RateLimiterGuard;
use c975L\UiBundle\Service\ReviewService;
use c975L\UiBundle\Service\ReviewTokenSigner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The two gates the public route closes before anything else happens - what is rendered past them needs a Twig runtime, and is checked by the templates' own tests
class ReviewControllerTest extends TestCase
{
    // What the form is built on, a signed-in visitor finding their address there and a visitor signed out an empty field
    #[DataProvider('prefilledAddresses')]
    public function testTheFormIsPrefilledWithTheAddressOfTheAccount(?string $address): void
    {
        $captured = (object) ['review' => null];
        $controller = new class ($this->createConfiguredStub(ReviewService::class, ['isEnabled' => true]), self::signer(), $this->createConfiguredStub(FavoriteItemRegistry::class, ['resolve' => [['ownerType' => 'book', 'ownerId' => 12, 'item' => new CollectionItem(title: 'x')]]]), $this->createStub(FormBotProtection::class), $this->createStub(RateLimiterGuard::class), $this->createStub(TranslatorInterface::class), null, $this->createConfiguredStub(CurrentAccountAddress::class, ['address' => $address]), $captured, $this->createStub(FormInterface::class)) extends ReviewController {
            public function __construct(ReviewService $a, ReviewTokenSigner $b, FavoriteItemRegistry $c, FormBotProtection $d, RateLimiterGuard $e, TranslatorInterface $f, ?RateLimiterFactoryInterface $g, CurrentAccountAddress $h, private readonly object $captured, private readonly FormInterface $form)
            {
                parent::__construct($a, $b, $c, $d, $e, $f, $g, $h);
            }

            protected function createForm(string $type, mixed $data = null, array $options = []): FormInterface
            {
                $this->captured->review = $data;

                return $this->form;
            }

            protected function generateUrl(string $route, array $parameters = [], int $referenceType = 1): string
            {
                return '/review';
            }

            protected function render(string $view, array $parameters = [], ?Response $response = null): Response
            {
                return new Response();
            }
        };

        $controller->new(self::token(), new Request());

        $this->assertSame($address, $captured->review->getAuthorEmail());
    }

    /** @return iterable<string, array{?string}> */
    public static function prefilledAddresses(): iterable
    {
        yield 'signed in' => ['jean@example.org'];
        yield 'signed out' => [null];
    }

    // A site that collects no reviews serves no page to write one on, rather than a form whose submissions nobody would ever read
    public function testTheRouteIsNotServedWhileTheFeatureIsOff(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(enabled: false)->new(self::token(), new Request());
    }

    // An id nobody claims, or one whose owner is not published: there is nothing to review, and nothing to name the page after
    public function testAnOwnerNobodyResolvesIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(resolved: [])->new(self::token(), new Request());
    }

    // A token nobody signed names nothing at all, and is answered exactly like an id nobody claims - without it the route would resolve whatever a visitor chose to write in the url
    public function testAForgedTokenIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->new('Ym9vazoxMw.0000000000000000', new Request());
    }

    // The bot check must not even be reached on a page that is not served: it would start a timer in a session for a form nobody gets
    public function testTheBotTimerIsNotStartedOnARouteThatIsNotServed(): void
    {
        $botProtection = $this->createMock(FormBotProtection::class);
        $botProtection->expects($this->never())->method('startTimer');

        try {
            $this->controller(enabled: false, botProtection: $botProtection)->new(self::token(), new Request());
        } catch (NotFoundHttpException) {
            // The gate is what this checks; the expectation above is what witnesses it
        }
    }

    // A token this site signed, naming the book the three tests above ask for
    private static function token(): string
    {
        return self::signer()->sign('book', 12);
    }

    private static function signer(): ReviewTokenSigner
    {
        return new ReviewTokenSigner('a-secret');
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
            self::signer(),
            $favoriteItemRegistry,
            $botProtection ?? $this->createStub(FormBotProtection::class),
            $this->createStub(RateLimiterGuard::class),
            $this->createStub(TranslatorInterface::class),
        );
    }
}
