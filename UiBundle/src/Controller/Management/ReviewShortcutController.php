<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management;

use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

// Flips the "ui-enable-reviews" config from the dashboard rather than from the Config screen, the one key deciding at once whether visitors may write a review, whether the published ones show on a sheet, and whether the moderation screen exists at all (see ReviewService::isEnabled()) - a switch that central is looked for on the dashboard, not in a list of a hundred settings
class ReviewShortcutController extends AbstractController
{
    // EasyAdmin prefixes this with the Dashboard's own route name, giving management_ui_reviews_toggle
    public const string TOGGLE_ROUTE = 'management_ui_reviews_toggle';

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly EntityManagerInterface $manager,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute(
        path: '/ui/reviews-toggle',
        name: 'ui_reviews_toggle',
        options: ['methods' => ['POST']]
    )]
    public function toggle(Request $request): RedirectResponse
    {
        // Opening a site to what visitors write about it is an admin's call, not an editor's - the same role the tile is drawn for (see UiShortcutProvider)
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        $config = $this->configRepository->findOneBySlug('ui-enable-reviews');
        if (null !== $config && $this->isCsrfTokenValid(self::TOGGLE_ROUTE, $request->request->get('_token'))) {
            $enabled = !$this->configService->getBool($config->getValue());
            $config->setValue($enabled);
            $config->setModification(new \DateTime());

            $this->manager->flush();
            $this->configService->invalidateCache();

            $this->addFlash('success', $this->translator->trans(
                $enabled ? 'flash.reviews_enabled' : 'flash.reviews_disabled',
                [],
                'ui',
            ));
        }

        return $this->redirectToRoute('management');
    }
}
