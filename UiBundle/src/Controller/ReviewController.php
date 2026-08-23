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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

// A page of its own rather than a form on the reviewed page: what is reviewed is a listing page, whose html a bundle is free to hand to a shared cache, where a form needs a session, a csrf token and a Set-Cookie - the three things that must never travel with a cached page. Leaving a review is also rare enough that one click to get there costs nothing, and the page then has room to say what becomes of what is written on it
class ReviewController extends AbstractController
{
    private const string SESSION_KEY = 'ui_review_started_at';

    public function __construct(
        private readonly ReviewService $reviewService,
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
        '/review/{ownerType}/{ownerId}',
        name: 'ui_review_new',
        requirements: [
            'ownerType' => '[a-z][a-z0-9_]{0,49}',
            'ownerId' => '\d+',
        ],
        methods: ['GET', 'POST']
    )]
    public function new(string $ownerType, int $ownerId, Request $request): Response
    {
        if (!$this->reviewService->isEnabled()) {
            throw $this->createNotFoundException();
        }

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
        $form = $this->createForm(ReviewType::class, $review);

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

                return $this->redirect($resolved['item']->url ?? $this->generateUrl('ui_review_new', ['ownerType' => $ownerType, 'ownerId' => $ownerId]));
            }
        }

        // A suspicious submission is answered exactly like a first display, and stored nowhere
        return $this->render('@c975LUi/review/new.html.twig', [
            'form' => $form->createView(),
            'item' => $resolved['item'],
            'ownerType' => $ownerType,
            'ownerId' => $ownerId,
        ]);
    }
}
