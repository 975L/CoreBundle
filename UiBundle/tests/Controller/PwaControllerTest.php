<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\PwaController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Twig\PwaExtension;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// The installable web app: a manifest built from the site's own settings, and nothing at all while the site keeps it off
class PwaControllerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-pwa-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
        $imagine = new Imagine();
        $imagine->create(new Box(512, 512), new RGB()->color('#ff0000'))->save($this->projectDir . '/public/app-icon.png');
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->projectDir . '/public/*'));
        rmdir($this->projectDir . '/public');
        rmdir($this->projectDir);
    }

    public function testTheManifestIsBuiltFromTheSiteSettings(): void
    {
        $manifest = $this->manifest(['ui-pwa-short-name' => 'Papa']);

        $this->assertSame('Papa Câlin', $manifest['name']);
        $this->assertSame('Papa', $manifest['short_name']);
        $this->assertSame('#0c1f33', $manifest['background_color']);
        $this->assertSame('standalone', $manifest['display']);
    }

    // A name too long for a home screen is the site's own choice to shorten, its full name otherwise
    public function testTheShortNameFallsBackOnTheSiteName(): void
    {
        $this->assertSame('Papa Câlin', $this->manifest()['short_name']);
    }

    // Chrome offers the install only with a 192px and a 512px icon, the maskable one keeping the logo out of a white circle - all three drawn from the app icon
    public function testTheIconsAreAllDrawnFromTheAppIcon(): void
    {
        $icons = $this->manifest()['icons'];

        $this->assertSame(['/app-icon-192.png', '/app-icon.png', '/app-icon-maskable.png'], array_column($icons, 'src'));
        $this->assertSame(['192x192', '512x512', '512x512'], array_column($icons, 'sizes'));
        $this->assertSame(['any', 'any', 'maskable'], array_column($icons, 'purpose'));
    }

    // The packaging tools of the Play Store read hexadecimal colors only, whatever notation the site stores
    public function testTheColorsAreWrittenInHexadecimal(): void
    {
        $manifest = $this->manifest(['theme-color-primary' => 'rgb(28, 87, 140)', 'theme-color-background' => '#FFF']);

        $this->assertSame('#1c578c', $manifest['theme_color']);
        $this->assertSame('#ffffff', $manifest['background_color']);
        $this->assertSame('/', $manifest['id']);
    }

    public function testThe192IconIsCutFromTheAppIcon(): void
    {
        $response = $this->controller()->icon('192', new Request());
        $icon = new Imagine()->load((string) $response->getContent());

        $this->assertSame(192, $icon->getSize()->getWidth());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
    }

    // The logo shrinks into the safe zone, the bleed around it taking the icon's own corner color
    public function testTheMaskableIconKeepsTheLogoInTheSafeZone(): void
    {
        $icon = new Imagine()->load((string) $this->controller()->icon('maskable', new Request())->getContent());

        $this->assertSame(512, $icon->getSize()->getWidth());
        $this->assertSame('#ff0000', (string) $icon->getColorAt(new Point(5, 5)));
    }

    public function testTheIconIsNotFoundWithoutAnAppIcon(): void
    {
        unlink($this->projectDir . '/public/app-icon.png');
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->icon('192', new Request());
    }

    // The Play Store app is linked only once the site names its package and fingerprints
    public function testTheAssetLinksNameThePackageAndItsFingerprints(): void
    {
        $links = json_decode((string) $this->controller([
            'ui-pwa-android-package' => 'com.papacalin.app',
            'ui-pwa-android-fingerprint' => 'AA:BB, CC:DD',
        ])->assetLinks()->getContent(), true);

        $this->assertSame('com.papacalin.app', $links[0]['target']['package_name']);
        $this->assertSame(['AA:BB', 'CC:DD'], $links[0]['target']['sha256_cert_fingerprints']);
    }

    public function testTheAssetLinksAreNotFoundWithoutAPackage(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->assetLinks();
    }

    // The larger install dialog needs both, each left out until the site fills it
    public function testTheDescriptionAndScreenshotsAreDeclaredOnlyWhenSet(): void
    {
        $this->assertArrayNotHasKey('description', $this->manifest());
        $this->assertArrayNotHasKey('screenshots', $this->manifest());

        $manifest = $this->manifest(['ui-pwa-description' => 'Les histoires à écouter'], [
            new Media()->setFilename('medias/site/app-screenshot-1.webp')->setWidth('800')->setHeight('1733')->setMimeType('image/webp'),
            new Media()->setFilename('medias/site/app-screenshot-2.webp')->setWidth('1600')->setHeight('900')->setMimeType('image/webp'),
            new Media()->setFilename('medias/site/app-screenshot-3.webp'),
        ]);

        $this->assertSame('Les histoires à écouter', $manifest['description']);
        $this->assertSame(['/medias/site/app-screenshot-1.webp', '/medias/site/app-screenshot-2.webp'], array_column($manifest['screenshots'], 'src'));
        $this->assertSame(['narrow', 'wide'], array_column($manifest['screenshots'], 'form_factor'));
        $this->assertSame('800x1733', $manifest['screenshots'][0]['sizes']);
    }

    // No share target unless the site names the page receiving the links
    public function testTheShareTargetIsDeclaredOnlyWhenSet(): void
    {
        $this->assertArrayNotHasKey('share_target', $this->manifest());

        $shareTarget = $this->manifest(['ui-pwa-share-target' => '/shorten'])['share_target'];
        $this->assertSame('/shorten', $shareTarget['action']);
        $this->assertSame(['title' => 'title', 'text' => 'text', 'url' => 'url'], $shareTarget['params']);
    }

    public function testTheManifestIsNotFoundWhileTurnedOff(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(['ui-pwa-enabled' => 'false'])->manifest(new Request());
    }

    // Each deploy rebuilds the container, so the worker changes and its cache starts afresh
    public function testTheWorkerIsNamedAfterTheContainerBuild(): void
    {
        $response = $this->controller()->serviceWorker();

        $this->assertStringContainsString('const CACHE = "c975l-pwa-1759680000";', (string) $response->getContent());
        $this->assertSame('text/javascript', $response->headers->get('Content-Type'));
    }

    // A browser keeps a worker whose update fails, so turning the app off serves one that unregisters itself
    public function testTheWorkerUnregistersItselfWhileTurnedOff(): void
    {
        $response = $this->controller(['ui-pwa-enabled' => 'false'])->serviceWorker();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('self.registration.unregister()', (string) $response->getContent());
    }

    public function testTheLayoutFollowsTheSetting(): void
    {
        $this->assertTrue(new PwaExtension($this->configService([]))->isEnabled());
        $this->assertFalse(new PwaExtension($this->configService(['ui-pwa-enabled' => 'false']))->isEnabled());
    }

    private function manifest(array $configs = [], array $screenshots = []): array
    {
        return json_decode((string) $this->controller($configs, $screenshots)->manifest(new Request())->getContent(), true);
    }

    private function controller(array $configs = [], array $screenshots = []): PwaController
    {
        $repository = $this->createStub(MediaRepository::class);
        $repository->method('findBy')->willReturn($screenshots);
        $repository->method('findOneByRole')->willReturnCallback(static fn (string $role): Media => new Media()->setRole($role)->setFilename($role . '.png'));

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $name, array $parameters = []): string => 'ui_pwa_icon' === $name ? '/app-icon-' . $parameters['variant'] . '.png' : '/offline');

        $twig = new Environment(new FilesystemLoader());
        $twig->getLoader()->addPath(\dirname(__DIR__, 2) . '/templates', 'c975LUi');

        $container = new Container();
        $container->set('twig', $twig);
        $container->set('router', $router);
        $container->set('parameter_bag', new ContainerBag(new Container(new ParameterBag(['container.build_time' => 1759680000]))));

        $controller = new PwaController($this->configService($configs), $repository, $this->projectDir);
        $controller->setContainer($container);

        return $controller;
    }

    private function configService(array $configs): ConfigServiceInterface
    {
        $configs += [
            'ui-pwa-enabled' => 'true',
            'site-name' => 'Papa Câlin',
            'theme-color-primary' => '#1c578c',
            'theme-color-background' => '#0c1f33',
        ];
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => $configs[$key] ?? null);
        $configService->method('getBool')->willReturnCallback(static fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN));

        return $configService;
    }
}
