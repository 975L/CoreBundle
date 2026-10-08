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
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Makes the site an installable web app once "ui-pwa-enabled" is on: its manifest and icons, its service worker, the page shown when the network is gone and the proof linking it to its Play Store app. Everything but the worker answers 404 while it is off, the worker being replaced by one that unregisters itself, since a browser keeps a worker whose update fails
class PwaController extends AbstractController
{
    // The share of a maskable icon Android never crops, whatever shape the phone cuts it to
    private const float MASKABLE_SAFE_ZONE = 0.8;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly MediaRepository $mediaRepository,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // What the browser reads to install the site: its names, colors, icons and, when set, the page receiving shared links
    #[Route('/manifest.webmanifest', name: 'ui_pwa_manifest', methods: ['GET'])]
    public function manifest(Request $request): Response
    {
        $this->denyUnlessEnabled();

        $name = (string) $this->configService->get('site-name');
        $manifest = [
            'id' => '/',
            'name' => $name,
            'short_name' => trim((string) $this->configService->get('ui-pwa-short-name')) ?: $name,
            'lang' => $request->getDefaultLocale(),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'theme_color' => $this->hexColor('theme-color-primary'),
            'background_color' => $this->hexColor('theme-color-background'),
            'icons' => $this->icons(),
        ];

        // Both turn Chrome's install prompt into the larger dialog an app store shows, each left out until the site fills it
        $description = trim((string) $this->configService->get('ui-pwa-description'));
        if ('' !== $description) {
            $manifest['description'] = $description;
        }
        $screenshots = $this->screenshots();
        if ([] !== $screenshots) {
            $manifest['screenshots'] = $screenshots;
        }

        // Locks the installed app in one orientation, left free to follow the phone until the site picks one
        $orientation = (string) $this->configService->get('ui-pwa-orientation');
        if (\in_array($orientation, ['portrait', 'landscape'], true)) {
            $manifest['orientation'] = $orientation;
        }

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

    // What this browser keeps for offline use, listed by a script from the cache itself: the page is the same for everyone
    #[Route('/pwa-downloads', name: 'ui_pwa_downloads', methods: ['GET'])]
    public function downloads(): Response
    {
        $this->denyUnlessEnabled();

        return $this->render('@c975LUi/pwa/downloads.html.twig');
    }

    // The 192px and maskable icons Chrome requires and the 180px one iOS puts on its home screen, cut on the fly from the single 512px app icon so a site uploads nothing more. Their url carries the upload's date, hence the year-long cache
    #[Route('/app-icon-{variant}.png', name: 'ui_pwa_icon', requirements: ['variant' => '192|180|maskable'], methods: ['GET'])]
    public function icon(string $variant, Request $request): Response
    {
        $this->denyUnlessEnabled();

        $path = $this->appIconPath();
        if (null === $path) {
            throw $this->createNotFoundException();
        }

        $response = new Response();
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setImmutable();
        $response->setLastModified(new \DateTimeImmutable('@' . filemtime($path)));
        if ($response->isNotModified($request)) {
            return $response;
        }

        $imagine = new Imagine();
        $source = $imagine->open($path);
        $icon = match ($variant) {
            '192' => $source->thumbnail(new Box(192, 192), ImageInterface::THUMBNAIL_INSET),
            '180' => $this->opaque($imagine, $source->thumbnail(new Box(180, 180), ImageInterface::THUMBNAIL_INSET)),
            default => $this->maskable($imagine, $source),
        };

        $response->setContent($icon->get('png'));
        $response->headers->set('Content-Type', 'image/png');

        return $response;
    }

    // Proves the Play Store app and the site share an owner, without which the app shows Chrome's url bar: the package and its signing fingerprints as the Play Console lists them
    #[Route('/.well-known/assetlinks.json', name: 'ui_pwa_asset_links', methods: ['GET'])]
    public function assetLinks(): JsonResponse
    {
        $this->denyUnlessEnabled();

        $package = trim((string) $this->configService->get('ui-pwa-android-package'));
        $fingerprints = array_values(array_filter(array_map(trim(...), explode(',', (string) $this->configService->get('ui-pwa-android-fingerprint')))));
        if ('' === $package || [] === $fingerprints) {
            throw $this->createNotFoundException();
        }

        return new JsonResponse([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => $package,
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]]);
    }

    // The three sizes Chrome asks for before offering the install, all drawn from the app icon
    private function icons(): array
    {
        $media = $this->mediaRepository->findOneByRole(Media::ROLE_APP_ICON);
        if (null === $media?->getFilename()) {
            return [];
        }

        $version = null !== $media->getUpdatedAt() ? ['v' => $media->getUpdatedAt()->getTimestamp()] : [];

        return [
            ['src' => $this->generateUrl('ui_pwa_icon', ['variant' => '192'] + $version), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/' . $media->getFilename() . ([] !== $version ? '?v=' . $version['v'] : ''), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => $this->generateUrl('ui_pwa_icon', ['variant' => 'maskable'] + $version), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ];
    }

    // The screens uploaded as site graphics, in their upload order, those whose size was never measured left out since the manifest has to state it
    private function screenshots(): array
    {
        $screenshots = [];
        foreach ($this->mediaRepository->findBy(['role' => Media::ROLE_APP_SCREENSHOT], ['id' => 'ASC']) as $media) {
            $width = (int) $media->getWidth();
            $height = (int) $media->getHeight();
            if (null === $media->getFilename() || 0 === $width || 0 === $height) {
                continue;
            }

            $screenshots[] = [
                'src' => '/' . $media->getFilename(),
                'sizes' => $width . 'x' . $height,
                'type' => $media->getMimeType(),
                'form_factor' => $width < $height ? 'narrow' : 'wide',
            ];
        }

        return $screenshots;
    }

    // The app icon's file on disk, null when none was uploaded
    private function appIconPath(): ?string
    {
        $filename = $this->mediaRepository->findOneByRole(Media::ROLE_APP_ICON)?->getFilename();
        $path = $this->projectDir . '/public/' . $filename;

        return null !== $filename && is_file($path) ? $path : null;
    }

    // The icon shrunk into the safe zone, on its own corner color so the bleed Android crops away matches it - the background color when that corner is transparent
    private function maskable(Imagine $imagine, ImageInterface $source): ImageInterface
    {
        $size = $source->getSize()->getWidth();
        $corner = $source->getColorAt(new Point(0, 0));
        $bleed = 100 === $corner->getAlpha() ? $corner : new RGB()->color($this->hexColor('theme-color-background'));

        $inner = (int) round($size * self::MASKABLE_SAFE_ZONE);
        $offset = (int) (($size - $inner) / 2);
        $icon = $imagine->create(new Box($size, $size), $bleed);
        $icon->paste($source->copy()->resize(new Box($inner, $inner)), new Point($offset, $offset));

        return $icon;
    }

    // The icon on the background color, since iOS paints a transparent home screen icon black
    private function opaque(Imagine $imagine, ImageInterface $source): ImageInterface
    {
        $icon = $imagine->create($source->getSize(), new RGB()->color($this->hexColor('theme-color-background')));
        $icon->paste($source, new Point(0, 0));

        return $icon;
    }

    // A theme color in the hexadecimal form the Play Store packaging tools read, from the hex or rgb() notation the site stores - white for anything else
    private function hexColor(string $slug): string
    {
        $color = trim((string) $this->configService->get($slug));
        if (1 === preg_match('/^#[0-9a-f]{6}$/i', $color)) {
            return strtolower($color);
        }
        if (1 === preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $color, $matches)) {
            return strtolower('#' . $matches[1] . $matches[1] . $matches[2] . $matches[2] . $matches[3] . $matches[3]);
        }
        if (1 === preg_match('/^rgba?\(\s*(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})/i', $color, $matches)) {
            return \sprintf('#%02x%02x%02x', min(255, (int) $matches[1]), min(255, (int) $matches[2]), min(255, (int) $matches[3]));
        }

        return '#ffffff';
    }

    // Whether the site turns the app on
    private function isEnabled(): bool
    {
        return $this->configService->getBool($this->configService->get('ui-pwa-enabled'));
    }

    // Everything but the worker exists only while the site turns the app on
    private function denyUnlessEnabled(): void
    {
        if (!$this->isEnabled()) {
            throw $this->createNotFoundException();
        }
    }
}
