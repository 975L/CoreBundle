<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Makes the site an installable web app once "ui-pwa-enabled" is on: its manifest, its service worker and the page shown when the network is gone. The manifest and the offline page answer 404 while it is off, the worker being replaced by one that unregisters itself, since a browser keeps a worker whose update fails
class PwaController extends AbstractController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly MediaRepository $mediaRepository,
    ) {
    }

    // What the browser reads to install the site: its names, colors, icons and, when set, the page receiving shared links
    #[Route('/manifest.webmanifest', name: 'ui_pwa_manifest', methods: ['GET'])]
    public function manifest(Request $request): Response
    {
        $this->denyUnlessEnabled();

        $name = (string) $this->configService->get('site-name');
        $manifest = [
            'name' => $name,
            'short_name' => trim((string) $this->configService->get('ui-pwa-short-name')) ?: $name,
            'lang' => $request->getDefaultLocale(),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'theme_color' => $this->color('theme-color-primary', '#ffffff'),
            'background_color' => $this->color('theme-color-background', '#ffffff'),
            'icons' => $this->icons(),
        ];

        // Lets the phone's "Share" menu send a link to the site, as GET parameters a page of the site reads
        $shareTarget = trim((string) $this->configService->get('ui-pwa-share-target'));
        if ('' !== $shareTarget) {
            $manifest['share_target'] = [
                'action' => $shareTarget,
                'method' => 'GET',
                'enctype' => 'application/x-www-form-urlencoded',
                'params' => ['title' => 'title', 'text' => 'text', 'url' => 'url'],
            ];
        }

        $response = new JsonResponse($manifest);
        $response->headers->set('Content-Type', 'application/manifest+json');

        return $response;
    }

    // Served from the root, since a worker only controls the paths under its own, and versioned on the container build so each deploy installs it again
    #[Route('/sw.js', name: 'ui_pwa_service_worker', methods: ['GET'])]
    public function serviceWorker(): Response
    {
        $response = $this->isEnabled()
            ? $this->render('@c975LUi/pwa/sw.js.twig', [
                'offlineUrl' => $this->generateUrl('ui_pwa_offline'),
                'version' => (string) $this->getParameter('container.build_time'),
            ])
            : $this->render('@c975LUi/pwa/sw-unregister.js.twig');
        $response->headers->set('Content-Type', 'text/javascript');
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }

    // Kept by the worker at its install, shown in place of any page the network could not bring
    #[Route('/offline', name: 'ui_pwa_offline', methods: ['GET'])]
    public function offline(): Response
    {
        $this->denyUnlessEnabled();

        return $this->render('@c975LUi/pwa/offline.html.twig');
    }

    // The 512px icon Android installs from, the apple-touch-icon as a fallback, too small for Chrome to offer the install but still drawn by the others
    private function icons(): array
    {
        $icons = [];
        foreach ([Media::ROLE_APP_ICON, Media::ROLE_APPLE_TOUCH_ICON] as $role) {
            $media = $this->mediaRepository->findOneByRole($role);
            $spec = Media::getFixedIconSpecs()[$role];
            if (null !== $media?->getFilename()) {
                $icons[] = [
                    'src' => '/' . $media->getFilename(),
                    'sizes' => $spec['width'] . 'x' . $spec['height'],
                    'type' => 'image/png',
                    'purpose' => 'any',
                ];
            }
        }

        return $icons;
    }

    // A theme color as the site stores it, any CSS notation a manifest accepts
    private function color(string $slug, string $default): string
    {
        return trim((string) $this->configService->get($slug)) ?: $default;
    }

    // Whether the site turns the app on
    private function isEnabled(): bool
    {
        return $this->configService->getBool($this->configService->get('ui-pwa-enabled'));
    }

    // The manifest and the offline page exist only while the site turns the app on
    private function denyUnlessEnabled(): void
    {
        if (!$this->isEnabled()) {
            throw $this->createNotFoundException();
        }
    }
}
