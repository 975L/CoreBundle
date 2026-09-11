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
use c975L\UiBundle\Listener\VichPdfThumbnailListener;
use c975L\UiBundle\Security\Voter\MediaVoter;
use c975L\UiBundle\Service\PrivateFileResponseFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

// Serves a media reserved to members from outside public/, where the web server cannot reach it (see Media::MEMBERS_ONLY_DIRECTORY). Guarded here rather than by a site's access_control: a site forgetting the rule would leave the file open, where this route asks nothing of the site at all
class MediaController extends AbstractController
{
    public const string ROUTE = 'ui_media_file';

    public const string THUMBNAIL_ROUTE = 'ui_media_thumbnail';

    // What stands in for the first page of a document the visitor may not open
    private const string LOCKED_THUMBNAIL = __DIR__ . '/../../public/images/document-locked.svg';

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

    // The first page of a document reserved to members, for the card linking to it (see DocumentExtension::getThumbnailUrl()): the thumbnail itself to who may open the document, a lock to anybody else. One url answering both rather than a template choosing, a block being rendered once and cached for every visitor. Refused by nothing either: an <img> sent to the login form shows a broken picture, not a lock
    #[Route(
        path: '/media/{id:media}/thumbnail',
        requirements: ['id' => '\d+'],
        name: self::THUMBNAIL_ROUTE,
        methods: ['GET']
    )]
    public function thumbnail(Media $media): Response
    {
        if (!$media->isMembersOnly()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isGranted(MediaVoter::VIEW, $media)) {
            return $this->lockedThumbnail();
        }

        $response = $this->privateFileResponseFactory->createInlineResponse(
            $this->projectDir . '/' . Media::MEMBERS_ONLY_DIRECTORY . '/' . VichPdfThumbnailListener::toWebpPath((string) $media->getFilename())
        );

        if (null === $response) {
            throw $this->createNotFoundException();
        }

        return $response;
    }

    // The same url answers a member with the thumbnail, so the lock is never kept: nor stored, nor dated - a Last-Modified would let the browser revalidate it after signing in and be told to keep it. The type is stated, a guess reading an svg as text and nosniff then refusing it as an image
    private function lockedThumbnail(): BinaryFileResponse
    {
        // A lock left out of the package answers 404, not the 500 BinaryFileResponse throws on a missing file
        if (!is_file(self::LOCKED_THUMBNAIL)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse(self::LOCKED_THUMBNAIL, Response::HTTP_OK, ['Content-Type' => 'image/svg+xml'], false, null, false, false);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        return $response;
    }
}
