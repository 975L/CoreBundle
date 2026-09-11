<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Security\Voter\MediaVoter;
use c975L\UiBundle\Service\PrivateFileResponseFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Serves a media reserved to members from outside public/, where the web server cannot reach it (see Media::MEMBERS_ONLY_DIRECTORY). Guarded here rather than by a site's access_control: a site forgetting the rule would leave the file open, where this route asks nothing of the site at all
class MediaController extends AbstractController
{
    public const string ROUTE = 'ui_media_file';

    public function __construct(
        private readonly PrivateFileResponseFactoryInterface $privateFileResponseFactory,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // Opened in place, like any pdf behind a link: an anonymous visitor is sent to the login form by the firewall, and brought back here once signed in. A public media answers 404, its one address being the web server's own. Not named "file", which AbstractController already declares
    #[Route(
        path: '/media/{id:media}',
        requirements: ['id' => '\d+'],
        name: self::ROUTE,
        methods: ['GET']
    )]
    public function open(Media $media): Response
    {
        if (!$media->isMembersOnly()) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(MediaVoter::VIEW, $media);

        $response = $this->privateFileResponseFactory->createInlineResponse(
            $this->projectDir . '/' . Media::MEMBERS_ONLY_DIRECTORY . '/' . $media->getFilename()
        );

        if (null === $response) {
            throw $this->createNotFoundException();
        }

        return $response;
    }
}
