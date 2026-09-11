<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Controller\MediaController;
use c975L\UiBundle\Entity\Media;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

class MediaUrlExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UploaderHelperInterface $uploaderHelper,
    ) {
    }

    // Where a media's file is reached from: the web server's own address for a public one, MediaController's route for one reserved to members - vich_uploader_asset() would name a file public/ no longer holds
    #[AsTwigFunction('media_url')]
    public function getUrl(Media $media): ?string
    {
        if ($media->isMembersOnly()) {
            return $this->urlGenerator->generate(MediaController::ROUTE, ['id' => $media->getId()]);
        }

        return $this->uploaderHelper->asset($media);
    }
}
