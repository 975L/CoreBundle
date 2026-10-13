<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use Twig\Attribute\AsTwigFunction;

// Whether the layout declares the site's manifest and registers its service worker (see PwaController), and what the "app_install" block shows of the app
class PwaExtension
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly MediaRepository $mediaRepository,
    ) {
    }

    #[AsTwigFunction('pwa_enabled')]
    public function isEnabled(): bool
    {
        return $this->configService->getBool($this->configService->get('ui-pwa-enabled'));
    }

    // The app's Play Store listing, read off the package the Digital Asset Links already name - null until one is set
    #[AsTwigFunction('pwa_play_store_url')]
    public function playStoreUrl(): ?string
    {
        $package = trim((string) $this->configService->get('ui-pwa-android-package'));

        return '' === $package ? null : 'https://play.google.com/store/apps/details?id=' . rawurlencode($package);
    }

    // The screens uploaded as site graphics for the manifest, in their upload order
    /** @return list<Media> */
    #[AsTwigFunction('pwa_screenshots')]
    public function screenshots(): array
    {
        return array_values(array_filter(
            $this->mediaRepository->findBy(['role' => Media::ROLE_APP_SCREENSHOT], ['id' => 'ASC']),
            static fn (Media $media): bool => null !== $media->getFilename()
        ));
    }
}
