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
use c975L\UiBundle\Controller\Management\SiteGraphicCrudController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Alerts for the site-wide graphics (favicon, apple-touch-icon, og-image, logo, and app-icon once the site is an installable app) not yet uploaded
class SiteGraphicAlertProvider implements AlertProviderInterface
{
    // Only the graphics every site owes itself: the dark logo and the two watermarks are deliberately absent, being answers to a situation a given site may never be in - a dashboard warning about a file the design has no use for is a warning that gets ignored
    private const array ROLE_LABELS = [
        Media::ROLE_FAVICON => 'label.favicon',
        Media::ROLE_APPLE_TOUCH_ICON => 'label.apple_touch_icon',
        Media::ROLE_OG_IMAGE => 'label.og_image',
        Media::ROLE_LOGO => 'label.logo',
    ];

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getAlerts(): array
    {
        $alerts = [];

        // The app icon is optional until the site turns the app on, Chrome offering no install without it
        $roleLabels = self::ROLE_LABELS;
        if ($this->configService->getBool($this->configService->get('ui-pwa-enabled'))) {
            $roleLabels[Media::ROLE_APP_ICON] = 'label.app_icon';
        }

        foreach ($roleLabels as $role => $labelKey) {
            if (null !== $this->mediaRepository->findOneByRole($role)) {
                continue;
            }

            $alerts[] = [
                'label' => $this->translator->trans($labelKey, [], 'ui'),
                'description' => $this->translator->trans('label.site_graphic_missing', [], 'ui'),
                'severity' => Config::SEVERITY_WARNING,
                // Straight to the upload form with the role already picked, the same target as the index buttons
                'url' => $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(SiteGraphicCrudController::class)
                    ->setAction(Action::NEW)
                    ->set(SiteGraphicCrudController::ROLE_PARAMETER, $role)
                    ->generateUrl(),
            ];
        }

        return $alerts;
    }
}
