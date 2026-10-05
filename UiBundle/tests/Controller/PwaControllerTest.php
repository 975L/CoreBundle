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

    // The 512px icon is what Android installs from, the apple-touch-icon following it
    public function testTheAppIconComesFirst(): void
    {
        $icons = $this->manifest()['icons'];

        $this->assertSame(['/app-icon.png', '/apple-touch-icon.png'], array_column($icons, 'src'));
        $this->assertSame('512x512', $icons[0]['sizes']);
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

    private function manifest(array $configs = []): array
    {
        return json_decode((string) $this->controller($configs)->manifest(new Request())->getContent(), true);
    }

    private function controller(array $configs = []): PwaController
    {
        $repository = $this->createStub(MediaRepository::class);
        $repository->method('findOneByRole')->willReturnCallback(static fn (string $role): Media => new Media()->setRole($role)->setFilename($role . '.png'));

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/offline');

        $twig = new Environment(new FilesystemLoader());
        $twig->getLoader()->addPath(\dirname(__DIR__, 2) . '/templates', 'c975LUi');

        $container = new Container();
        $container->set('twig', $twig);
        $container->set('router', $router);
        $container->set('parameter_bag', new ContainerBag(new Container(new ParameterBag(['container.build_time' => 1759680000]))));

        $controller = new PwaController($this->configService($configs), $repository);
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
