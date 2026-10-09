<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use c975L\ConfigBundle\Management\FeedRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Serves the Atom feed each FeedProviderInterface contributes, written in the site's default language
class FeedController extends AbstractController
{
    // How long a feed reader or a proxy may keep the document without asking again
    private const int MAX_AGE = 3600;

    public function __construct(private readonly FeedRenderer $feedRenderer)
    {
    }

    // A feed unknown, empty or closed (see FeedRenderer::isClosed()) answers the same 404
    #[Route('/feed/{name}.xml', name: 'config_feed', requirements: ['name' => '[a-z]+'], methods: ['GET'])]
    public function display(Request $request, string $name): Response
    {
        $content = $this->feedRenderer->render($name, $request->getDefaultLocale());
        if (null === $content) {
            throw $this->createNotFoundException();
        }

        $response = new Response($content, Response::HTTP_OK, ['Content-Type' => 'application/atom+xml; charset=utf-8']);
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);

        return $response;
    }
}
