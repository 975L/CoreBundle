<?php

/*
 * (c) 2018: 975L <contact@975l.com>
 * (c) 2018: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle;

use c975L\UiBundle\DependencyInjection\Compiler\BlockCacheTagProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\BlockEditUrlProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\BlockFixtureProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\BlockOwnerResolverPass;
use c975L\UiBundle\DependencyInjection\Compiler\BlockRegistryPass;
use c975L\UiBundle\DependencyInjection\Compiler\CollectionSourceProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\EmailLayoutProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\FontProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\FormActionProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\FormThemeRegistryPass;
use c975L\UiBundle\DependencyInjection\Compiler\GalleryShowcaseProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\MediaUsageProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\PlaceholderMediaProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\ScriptAdminRegistryPass;
use c975L\UiBundle\DependencyInjection\Compiler\ScriptRegistryPass;
use c975L\UiBundle\DependencyInjection\Compiler\StylesheetManagementRegistryPass;
use c975L\UiBundle\DependencyInjection\Compiler\StylesheetRegistryPass;
use c975L\UiBundle\DependencyInjection\Compiler\WhatsNewProviderPass;
use c975L\UiBundle\Namer\UiMediaNamer;
use c975L\UiBundle\Storage\NestedFileSystemStorage;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class c975LUiBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new BlockRegistryPass());
        $container->addCompilerPass(new BlockFixtureProviderPass());
        $container->addCompilerPass(new PlaceholderMediaProviderPass());
        $container->addCompilerPass(new BlockOwnerResolverPass());
        $container->addCompilerPass(new BlockCacheTagProviderPass());
        $container->addCompilerPass(new CollectionSourceProviderPass());
        $container->addCompilerPass(new GalleryShowcaseProviderPass());
        $container->addCompilerPass(new StylesheetRegistryPass());
        $container->addCompilerPass(new StylesheetManagementRegistryPass());
        $container->addCompilerPass(new ScriptRegistryPass());
        $container->addCompilerPass(new ScriptAdminRegistryPass());
        $container->addCompilerPass(new WhatsNewProviderPass());
        $container->addCompilerPass(new MediaUsageProviderPass());
        $container->addCompilerPass(new BlockEditUrlProviderPass());
        $container->addCompilerPass(new EmailLayoutProviderPass());
        $container->addCompilerPass(new FontProviderPass());
        $container->addCompilerPass(new FormThemeRegistryPass());
        $container->addCompilerPass(new FormActionProviderPass());
    }

    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    __DIR__ . '/../assets' => '@c975l/ui-bundle',
                ],
            ],
        ]);

        // The admin form themes are NOT registered here: EasyAdmin renders every CRUD form with "... only", which ignores this config entirely (see FormThemeProviderInterface) - they are instead contributed to FormThemeRegistry via UiFormThemeProvider and picked up by ConfigBundle's DashboardController::configureCrud(). CaptchaType only ever appears in public forms, which are rendered by plain Twig, so the app-wide config is the right place for it (and what karser/karser-recaptcha3-bundle did for the widget this one replaces)
        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'form_themes' => ['@c975LUi/form/captcha_theme.html.twig'],
                // Registers public/css as a Twig namespace so a compiled stylesheet can be embedded raw via
                // source(). An email carries no <link>, its CSS having to travel inside the message itself:
                // this is how a bundle's own email layout pulls emails.min.css in before inlining it
                'paths' => [
                    __DIR__ . '/../public/css' => 'c975LUiCss',
                ],
            ]);
        }

        if ($container->hasExtension('vich_uploader')) {
            $container->prependExtensionConfig('vich_uploader', [
                // Lets namers (see UiMediaNamer/getVichMediaPath) return a path with subdirectories (e.g. "medias/site/block-article-42-xxx.webp") that is both the value stored in "filename" and the file's real location on disk - Vich's own storage silently flattens such paths on upload (see NestedFileSystemStorage for why).
                'storage' => '@' . NestedFileSystemStorage::class,
                'mappings' => [
                    'block_media' => [
                        'uri_prefix' => '',
                        'upload_destination' => '%kernel.project_dir%/public',
                        'namer' => UiMediaNamer::class,
                        'inject_on_load' => false,
                        'delete_on_update' => true,
                        'delete_on_remove' => true,
                    ],
                ],
            ]);
        }
    }

    public function loadExtension(array $config, ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void
    {
        $containerConfigurator->import('../config/services.yaml');

        // symfony/maker-bundle is dev-only in a consuming app - only wire MakeBlockCommand as a service when it's actually installed, instead of requiring it unconditionally
        if (class_exists(\Symfony\Bundle\MakerBundle\Maker\AbstractMaker::class)) {
            $containerConfigurator->import('../config/services_maker.yaml');
        }
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
