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
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class c975LUiBundleTest extends TestCase
{
    // CaptchaType's widget only ever appears in public forms, rendered by plain Twig, so it goes through the app-wide twig.form_themes config (unlike the admin themes, which EasyAdmin ignores - see FormThemeRegistry)
    public function testPrependExtensionRegistersTheCaptchaFormTheme(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extension('twig'));

        (new c975LUiBundle())->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $config = $container->getExtensionConfig('twig');

        $this->assertSame(['@c975LUi/form/captcha_theme.html.twig'], $config[0]['form_themes']);
    }

    // An email carries no <link>, so its stylesheet has to travel inside the message: the namespace is what
    // lets a bundle's own email layout source() the compiled emails.min.css before inlining it
    public function testPrependExtensionRegistersTheCompiledCssNamespace(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extension('twig'));

        (new c975LUiBundle())->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $paths = $container->getExtensionConfig('twig')[0]['paths'];

        $this->assertSame(['c975LUiCss'], array_values($paths));
        $this->assertFileExists(array_key_first($paths) . '/emails.min.css', 'The namespace points at a directory holding no compiled emails.min.css.');
    }

    // An app without TwigBundle (an API-only one, say) must still boot: prepending config for an unregistered extension throws
    public function testPrependExtensionSkipsTheFormThemeWithoutTwig(): void
    {
        $container = new ContainerBuilder();

        (new c975LUiBundle())->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $this->assertSame([], $container->getExtensionConfig('twig'));
    }

    // The asset_mapper path is the one prepend that has no condition - framework is always there
    public function testPrependExtensionRegistersTheAssetMapperPath(): void
    {
        $container = new ContainerBuilder();

        (new c975LUiBundle())->prependExtension($this->createStub(ContainerConfigurator::class), $container);

        $paths = $container->getExtensionConfig('framework')[0]['asset_mapper']['paths'];

        $this->assertSame(['@c975l/ui-bundle'], array_values($paths));
    }

    // Both captcha compiler passes went away with karser/karser-recaptcha3-bundle - nothing may still reference them
    public function testBuildRegistersNoCaptchaCompilerPass(): void
    {
        $container = new ContainerBuilder();

        (new c975LUiBundle())->build($container);

        $passes = array_map(
            static fn (object $pass): string => $pass::class,
            $container->getCompiler()->getPassConfig()->getBeforeOptimizationPasses()
        );

        $this->assertNotContains('c975L\UiBundle\DependencyInjection\Compiler\RecaptchaPass', $passes);
        $this->assertNotContains('c975L\UiBundle\DependencyInjection\Compiler\CspListenerPass', $passes);
    }

    // A bare extension, just enough for hasExtension() to answer true
    private function extension(string $alias): ExtensionInterface
    {
        return new class ($alias) implements ExtensionInterface {
            public function __construct(private readonly string $alias)
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
