<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Management\AlertProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\ReviewCrudController;
use c975L\UiBundle\Repository\ReviewRepository;
use c975L\UiBundle\Service\ReviewService;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// What a review waiting for a decision says on the dashboard: nothing of it shows on the site until someone reads it, so an unread one is a visitor left without an answer rather than a queue that empties itself
class ReviewAlertProvider implements AlertProviderInterface
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly ReviewService $reviewService,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getAlerts(): array
    {
        // Same switch as the screen it links to (see MenuProvider): a site collecting no reviews has none waiting
        if (!$this->reviewService->isEnabled()) {
            return [];
        }

        $pending = $this->reviewRepository->countPending();
        if (0 === $pending) {
            return [];
        }

        return [[
            'label' => $this->translator->trans('label.reviews_pending_alert', ['%count%' => $pending], 'ui'),
            'description' => $this->translator->trans('description.reviews_pending_alert', [], 'ui'),
            'severity' => Config::SEVERITY_WARNING,
            // The bar ReviewCrudController states on its own rows, the same one the sidebar entry carries
            'role' => $this->configService->get('site-role-editor'),
            'url' => $this->adminUrlGenerator
                ->unsetAll()
                ->setController(ReviewCrudController::class)
                ->generateUrl(),
        ]];
    }
}
