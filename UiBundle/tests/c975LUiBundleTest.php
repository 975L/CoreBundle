<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests;

use c975L\UiBundle\c975LUiBundle;
use c975L\UiBundle\DependencyInjection\Compiler\PlaceholderMediaProviderPass;
use c975L\UiBundle\Video\VideoPlatform;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class c975LUiBundleTest extends TestCase
{
    // CaptchaType's widget only ever appears in public forms, rendered by plain Twig, so it goes through the app-wide twig.form_themes config (unlike the admin themes, which EasyAdmin ignores - see FormThemeRegistry)
    public function testPrependExtensionRegistersTheCaptchaFormTheme(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extension('twig'));

        new c975LUiBundle()->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $config = $container->getExtensionConfig('twig');

        $this->assertSame(['@c975LUi/form/captcha_theme.html.twig'], $config[0]['form_themes']);
    }

    // An email carries no <link>, so its stylesheet has to travel inside the message: the namespace is what lets a bundle's own email layout source() the compiled emails.min.css before inlining it
    public function testPrependExtensionRegistersTheCompiledCssNamespace(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extension('twig'));

        new c975LUiBundle()->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $paths = $container->getExtensionConfig('twig')[0]['paths'];

        $this->assertSame(['c975LUiCss'], array_values($paths));
        $this->assertFileExists(array_key_first($paths) . '/emails.min.css', 'The namespace points at a directory holding no compiled emails.min.css.');
    }

    // An app without TwigBundle (an API-only one, say) must still boot: prepending config for an unregistered extension throws
    public function testPrependExtensionSkipsTheFormThemeWithoutTwig(): void
    {
        $container = new ContainerBuilder();

        new c975LUiBundle()->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $this->assertSame([], $container->getExtensionConfig('twig'));
    }

    // The asset_mapper path is the one prepend that has no condition - framework is always there
    public function testPrependExtensionRegistersTheAssetMapperPath(): void
    {
        $container = new ContainerBuilder();

        new c975LUiBundle()->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $paths = $container->getExtensionConfig('framework')[0]['asset_mapper']['paths'];

        $this->assertSame(['@c975l/ui-bundle'], array_values($paths));
    }

    // FormController takes its limiter optionally, so a site that never declared one served registration and password reset unlimited - a default belongs here, where no site can forget it
    public function testPrependExtensionRegistersTheGenericFormRateLimiter(): void
    {
        $container = new ContainerBuilder();

        new c975LUiBundle()->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $limiter = $container->getExtensionConfig('framework')[0]['rate_limiter']['ui_form'];

        $this->assertSame('sliding_window', $limiter['policy']);
        $this->assertSame(5, $limiter['limit']);
        $this->assertSame('10 minutes', $limiter['interval']);
    }

    // RatingController takes its limiter optionally too, and its route is public and unauthenticated: with no default here, a site that declared nothing served the vote endpoint unlimited
    public function testPrependExtensionRegistersTheRatingRateLimiter(): void
    {
        $container = new ContainerBuilder();

        new c975LUiBundle()->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $limiter = $container->getExtensionConfig('framework')[0]['rate_limiter']['ui_rating'];

        $this->assertSame('sliding_window', $limiter['policy']);
        $this->assertSame(30, $limiter['limit']);
        $this->assertSame('10 minutes', $limiter['interval']);
    }

    // Each name is the contract with a controller's own "@?limiter.*" argument - a rename on either side silently restores the hole these defaults close
    public function testTheRateLimitersAreNamedAfterTheServicesTheControllersAskFor(): void
    {
        $container = new ContainerBuilder();

        new c975LUiBundle()->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $this->assertSame(['ui_form', 'ui_rating'], array_keys($container->getExtensionConfig('framework')[0]['rate_limiter']));

        $services = file_get_contents(__DIR__ . '/../config/services.yaml');
        $this->assertStringContainsString(
            '@?limiter.ui_form',
            $services,
            'FormController no longer asks for limiter.ui_form, which is the name prepended here.'
        );
        $this->assertStringContainsString(
            '@?limiter.ui_rating',
            $services,
            'RatingController no longer asks for limiter.ui_rating, which is the name prepended here.'
        );
    }

    // NelmioSecurityBundle registers its CSP listener under its own service id and aliases nothing on the class, so CspNonceProvider - which type-hints that class - cannot be autowired: a consuming app compiled a container until its first cache:clear, then died on "no such service exists"
    // Optional on top of explicit: that listener only exists once a site has configured a csp section, and a site without one must still boot
    public function testTheCspNonceProviderIsWiredOnNelmiosOwnServiceId(): void
    {
        $this->assertStringContainsString(
            "\$listener: '@?nelmio_security.csp_listener'",
            (string) file_get_contents(__DIR__ . '/../config/services.yaml'),
            'CspNonceProvider is left to autowiring again, which cannot resolve NelmioSecurityBundle\'s listener class.'
        );
    }

    // Every entity of this bundle carrying an uploaded file needs its mapping declared here - Font's used to come from SiteBundle, which an app running Config + Ui plus a satellite bundle doesn't have, and an upload then died on "No mapping named site_font configured"
    public function testPrependExtensionRegistersAVichMappingForEveryUploadingEntity(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extension('vich_uploader'));

        new c975LUiBundle()->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $mappings = $container->getExtensionConfig('vich_uploader')[0]['mappings'];

        $this->assertSame(['block_media', 'site_font'], array_keys($mappings));
        $this->assertSame($mappings['block_media'], $mappings['site_font'], 'Both mappings store into public/ through the same namer.');
    }

    // An app without VichUploaderBundle must still boot: prepending config for an unregistered extension throws
    public function testPrependExtensionSkipsTheVichMappingsWithoutVich(): void
    {
        $container = new ContainerBuilder();

        new c975LUiBundle()->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $this->assertSame([], $container->getExtensionConfig('vich_uploader'));
    }

    // Both captcha compiler passes went away with karser/karser-recaptcha3-bundle - nothing may still reference them
    public function testBuildRegistersNoCaptchaCompilerPass(): void
    {
        $container = new ContainerBuilder();

        new c975LUiBundle()->build($container);

        $passes = array_map(
            static fn (object $pass): string => $pass::class,
            $container->getCompiler()->getPassConfig()->getBeforeOptimizationPasses()
        );

        $this->assertNotContains('c975L\UiBundle\DependencyInjection\Compiler\RecaptchaPass', $passes);
        $this->assertNotContains('c975L\UiBundle\DependencyInjection\Compiler\CspListenerPass', $passes);
    }

    // A pass tested on its own but never registered here would leave PlaceholderMediaProviderInterface silently undiscovered
    public function testBuildRegistersThePlaceholderMediaProviderPass(): void
    {
        $container = new ContainerBuilder();

        new c975LUiBundle()->build($container);

        $passes = array_map(
            static fn (object $pass): string => $pass::class,
            $container->getCompiler()->getPassConfig()->getBeforeOptimizationPasses()
        );

        $this->assertContains(PlaceholderMediaProviderPass::class, $passes);
    }

    // A site builds its frame-src from this parameter rather than from a list copied out of the README, so declaring a platform never leaves it framed in development and blocked in production (see Video\VideoPlatform)
    public function testLoadExtensionExposesTheVideoEmbedOriginsAsAParameter(): void
    {
        $container = new ContainerBuilder();

        new c975LUiBundle()->loadExtension([], $this->containerConfigurator($container), $container);

        $origins = $container->getParameter('c975l_ui.video.embed_origins');

        // A space-separated string, which is what a CSP directive is: an array could only be a whole config node, never one item of a sequence next to the site's own origins
        $this->assertSame(implode(' ', VideoPlatform::allCspOrigins()), $origins);
        $this->assertStringContainsString('https://www.youtube-nocookie.com', (string) $origins);
    }

    // A real configurator over a stubbed loader: ContainerConfigurator::import() is final, so it cannot be stubbed away, and the service definitions it would pull in are not what this test reads
    private function containerConfigurator(ContainerBuilder $container): ContainerConfigurator
    {
        $instanceof = [];

        return new ContainerConfigurator($container, $this->createStub(PhpFileLoader::class), $instanceof, __DIR__, 'services.php');
    }

    // A bare extension, just enough for hasExtension() to answer true
    private function extension(string $alias): ExtensionInterface
    {
        return new readonly class ($alias) implements ExtensionInterface {
            public function __construct(private string $alias)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): string | false
            {
                return false;
            }

            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
