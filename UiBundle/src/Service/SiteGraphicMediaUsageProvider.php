<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\MediaUsageProviderInterface;
use c975L\UiBundle\Controller\Management\SiteGraphicCrudController;
use c975L\UiBundle\Entity\Media;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Resolves, for the Media library, the medias carrying a site-wide graphic role (favicon, logo...) - they belong to no Block, so BlockMediaUsageProvider skips them, and they are edited from their own screen rather than from a page
class SiteGraphicMediaUsageProvider implements MediaUsageProviderInterface
{
    private const array ROLE_LABELS = [
        Media::ROLE_FAVICON => 'label.favicon',
        Media::ROLE_APPLE_TOUCH_ICON => 'label.apple_touch_icon',
        Media::ROLE_OG_IMAGE => 'label.og_image',
        Media::ROLE_LOGO => 'label.logo',
        Media::ROLE_ERROR_IMAGE => 'label.error_image',
    ];

    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getUsages(array $medias): array
    {
        $usages = [];

        foreach ($medias as $media) {
            if (null === $media->getRole()) {
                continue;
            }

            $usages[$media->getId()][] = [
                'label' => $this->translator->trans(self::ROLE_LABELS[$media->getRole()] ?? $media->getRole(), [], 'ui'),
                'url' => $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(SiteGraphicCrudController::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId($media->getId())
                    ->generateUrl(),
                // A site-wide role has no bin to sit in: the favicon a site serves is served, so the verdict is given rather than left open (see MediaUsageProviderInterface) - which is what keeps a media carrying a role out of the library's hidden ones, whatever else holds it
                'binned' => false,
            ];
        }

        return $usages;
    }
}
