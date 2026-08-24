<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Form\ReviewType;
use c975L\UiBundle\Registry\FavoriteItemRegistry;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\RateLimiterGuard;
use c975L\UiBundle\Service\ReviewService;
use c975L\UiBundle\Service\ReviewTokenSigner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

// Served two ways from one route: as a page of its own, and - to the fold under the reviewed thing (see components/Review/List.html.twig) - as the form alone. What is reviewed is a listing page carrying no session and no cookie at all, and a form needs a csrf token and the Set-Cookie that comes with it: fetched only once a visitor opens the fold, none of it is ever handed to whoever merely reads the page
// The route names what is reviewed through a signed token and not through its id: a public url has no business printing a database id, and /review/book/1..n would have walked the whole catalog (see ReviewTokenSigner)
class ReviewController extends AbstractController
{
    private const string SESSION_KEY = 'ui_review_started_at';

    public function __construct(
        private readonly ReviewService $reviewService,
        private readonly ReviewTokenSigner $tokenSigner,
        private readonly FavoriteItemRegistry $favoriteItemRegistry,
        private readonly FormBotProtection $botProtection,
        private readonly RateLimiterGuard $rateLimiterGuard,
        private readonly TranslatorInterface $translator,
        private readonly ?RateLimiterFactoryInterface $reviewLimiterFactory = null,
    ) {
    }

    // Private and never stored: the page carries a session-bound honeypot and a csrf token, both of which would be served to the next visitor by any cache that kept them
    #[Cache(maxage: 0, public: false, mustRevalidate: true)]
    #[Route(
        '/review/{token}',
        name: 'ui_review_new',
        requirements: [
            'token' => '[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+',
        ],
        methods: ['GET', 'POST']
    )]
    public function new(string $token, Request $request): Response
    {
        if (!$this->reviewService->isEnabled()) {
            throw $this->createNotFoundException();
        }

        // A token this site did not sign names nothing, and is answered exactly like an id nobody claims
        $owner = $this->tokenSigner->unsign($token);
        if (null === $owner) {
            throw $this->createNotFoundException();
        }

        ['ownerType' => $ownerType, 'ownerId' => $ownerId] = $owner;

        // What the review is about, resolved by whoever owns that vocabulary - the same providers a wishlist reads its entries through, rather than a contract of its own. Nothing to resolve means nothing to review: an id nobody claims, or one whose owner is not published
        $resolved = $this->favoriteItemRegistry->resolve([$ownerType => [$ownerId]])[0] ?? null;
        if (null === $resolved) {
            throw $this->createNotFoundException();
        }

        $this->botProtection->startTimer($request, self::SESSION_KEY);

        $review = new Review()
            ->setOwnerType($ownerType)
            ->setOwnerId($ownerId)
        ;
        // Stated rather than left to the browser: injected into the page of the thing it is about (see components/Review/List.html.twig), a form with no action of its own posts to that page, which serves GET alone
        $form = $this->createForm(ReviewType::class, $review, [
            'action' => $this->generateUrl('ui_review_new', ['token' => $token]),
        ]);

        // Checked before handleRequest(), which is then skipped entirely so the bot gets the same answer and no hint - same reading as FormController's
        $suspicious = $request->isMethod('POST')
            && $this->botProtection->isSuspicious($request, $form->getName(), self::SESSION_KEY);

        if (!$suspicious) {
            $form->handleRequest($request);
        }

        if (!$suspicious && $form->isSubmitted() && $form->isValid()) {
            // Counted per caller and not per address, an IPv6 subscriber holding a block far larger than any ceiling could count - see RateLimiterGuard::isAcceptedForIp()
            $clientIp = $request->getClientIp();

            if (null !== $clientIp && !$this->rateLimiterGuard->isAcceptedForIp($this->reviewLimiterFactory, $clientIp)) {
                $this->addFlash('warning', $this->translator->trans('text.too_many_attempts', [], 'ui'));
            } else {
                $this->reviewService->submit($review);
                $this->addFlash('success', $this->translator->trans('text.review_submitted', [], 'ui'));

                return $this->redirect($resolved['item']->url ?? $this->generateUrl('ui_review_new', ['token' => $token]));
            }
        }

        $parameters = [
            'form' => $form->createView(),
            'item' => $resolved['item'],
            'ownerType' => $ownerType,
            'ownerId' => $ownerId,
        ];

        // The fold asks for the form alone, having a page around it already - the whole page is what a visitor reaching the url directly gets
        // A suspicious submission is answered exactly like a first display, and stored nowhere
        return $this->render(
            $request->isXmlHttpRequest() ? '@c975LUi/review/_form.html.twig' : '@c975LUi/review/new.html.twig',
            $parameters,
        );
    }
}
