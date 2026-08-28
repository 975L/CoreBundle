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
use c975L\UiBundle\DependencyInjection\Compiler\BlockLocationProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\BlockOwnerResolverPass;
use c975L\UiBundle\DependencyInjection\Compiler\BlockRegistryPass;
use c975L\UiBundle\DependencyInjection\Compiler\CacheInvalidatorPass;
use c975L\UiBundle\DependencyInjection\Compiler\CollectionSourceProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\DemoFixtureProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\EmailAttachmentProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\EmailLayoutProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\EmailTemplateProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\FavoriteItemProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\FontProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\FormActionProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\FormBlockDependencyProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\FormPageUrlProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\FormThemeRegistryPass;
use c975L\UiBundle\DependencyInjection\Compiler\GalleryShowcaseProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\MediaUsageProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\PlaceholderMediaProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\ReviewReplyPublisherPass;
use c975L\UiBundle\DependencyInjection\Compiler\ReviewVerifierPass;
use c975L\UiBundle\DependencyInjection\Compiler\SameAsProviderPass;
use c975L\UiBundle\DependencyInjection\Compiler\ScriptAdminRegistryPass;
use c975L\UiBundle\DependencyInjection\Compiler\ScriptRegistryPass;
use c975L\UiBundle\DependencyInjection\Compiler\StylesheetManagementRegistryPass;
use c975L\UiBundle\DependencyInjection\Compiler\StylesheetRegistryPass;
use c975L\UiBundle\DependencyInjection\Compiler\WhatsNewProviderPass;
use c975L\UiBundle\Namer\UiMediaNamer;
use c975L\UiBundle\Storage\NestedFileSystemStorage;
use c975L\UiBundle\Video\VideoPlatform;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class c975LUiBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new BlockRegistryPass());
        $container->addCompilerPass(new EmailTemplateProviderPass());
        $container->addCompilerPass(new BlockFixtureProviderPass());
        $container->addCompilerPass(new DemoFixtureProviderPass());
        $container->addCompilerPass(new PlaceholderMediaProviderPass());
        $container->addCompilerPass(new BlockOwnerResolverPass());
        $container->addCompilerPass(new BlockCacheTagProviderPass());
        $container->addCompilerPass(new CacheInvalidatorPass());
        $container->addCompilerPass(new CollectionSourceProviderPass());
        $container->addCompilerPass(new FavoriteItemProviderPass());
        $container->addCompilerPass(new GalleryShowcaseProviderPass());
        $container->addCompilerPass(new SameAsProviderPass());
        $container->addCompilerPass(new ReviewReplyPublisherPass());
        $container->addCompilerPass(new ReviewVerifierPass());
        $container->addCompilerPass(new StylesheetRegistryPass());
        $container->addCompilerPass(new StylesheetManagementRegistryPass());
        $container->addCompilerPass(new ScriptRegistryPass());
        $container->addCompilerPass(new ScriptAdminRegistryPass());
        $container->addCompilerPass(new WhatsNewProviderPass());
        $container->addCompilerPass(new MediaUsageProviderPass());
        $container->addCompilerPass(new BlockEditUrlProviderPass());
        $container->addCompilerPass(new BlockLocationProviderPass());
        $container->addCompilerPass(new EmailLayoutProviderPass());
        $container->addCompilerPass(new EmailAttachmentProviderPass());
        $container->addCompilerPass(new FontProviderPass());
        $container->addCompilerPass(new FormThemeRegistryPass());
        $container->addCompilerPass(new FormActionProviderPass());
        $container->addCompilerPass(new FormPageUrlProviderPass());
        $container->addCompilerPass(new FormBlockDependencyProviderPass());
    }

    public function prependExtension(ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    __DIR__ . '/../assets' => '@c975l/ui-bundle',
                ],
            ],
            // The limiter every generic Form shares, declared here rather than left to the consuming app: a Form built in the back office has no dedicated service of its own to bind a named limiter to, and FormController takes "@?limiter.ui_form" optionally, so a site that never declared it served its public forms - registration and password reset among them - with no limit at all, and nothing said so. An app declaring its own "ui_form" still decides, its config being merged over this one. Unguarded on purpose: symfony/rate-limiter is a hard dependency of this package, and an app that strips it anyway must fail on an unknown "rate_limiter" key rather than quietly lose the protection again
            'rate_limiter' => [
                'ui_form' => [
                    'policy' => 'sliding_window',
                    'limit' => 5,
                    'interval' => '10 minutes',
                ],
                // The public vote route (see RatingController), declared here for the same reason: it is served by this bundle and no site wires anything for it. Roomier than the form's - correcting a score, then rating the next book, is ordinary browsing, whereas five contact e-mails in ten minutes is not
                'ui_rating' => [
                    'policy' => 'sliding_window',
                    'limit' => 30,
                    'interval' => '10 minutes',
                ],
                // The wishlist's own routes (see FavoriteController), declared for the same reason. Roomier still than the vote's: putting five things aside, then coming back to the list, is one page of ordinary browsing
                'ui_favorite' => [
                    'policy' => 'sliding_window',
                    'limit' => 60,
                    'interval' => '10 minutes',
                ],
                // The public review form (see ReviewController), declared for the same reason. Far tighter than the three above: writing a review is a rare, deliberate act, and three an hour from one caller is already more than anyone has to say
                'ui_review' => [
                    'policy' => 'sliding_window',
                    'limit' => 3,
                    'interval' => '1 hour',
                ],
            ],
        ]);

        // The admin form themes are NOT registered here: EasyAdmin renders every CRUD form with "... only", which ignores this config entirely (see FormThemeProviderInterface) - they are instead contributed to FormThemeRegistry via UiFormThemeProvider and picked up by ConfigBundle's DashboardController::configureCrud(). CaptchaType only ever appears in public forms, which are rendered by plain Twig, so the app-wide config is the right place for it (and what karser/karser-recaptcha3-bundle did for the widget this one replaces)
        if ($container->hasExtension('twig')) {
            $container->prependExtensionConfig('twig', [
                'form_themes' => ['@c975LUi/form/captcha_theme.html.twig'],
                // Registers public/css as a Twig namespace so a compiled stylesheet can be embedded raw via source(). An email carries no <link>, its CSS having to travel inside the message itself: this is how a bundle's own email layout pulls emails.min.css in before inlining it
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
                    // Admin-uploaded font files (ttf/woff/woff2), stored under public/medias/fonts (see Font::getVichMediaPath) - see FontCssListener for the generated @font-face rules
                    'site_font' => [
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

        // Every origin the declared video platforms need framed, for a site to build its Content-Security-Policy from the registry rather than from a list copied out of a README - one entry of nelmio_security's frame-src (and of its child-src fallback), so declaring a new platform never leaves a site with an empty frame in production and none in development
        // A space-separated string rather than the array it is built from: a CSP directive is exactly that, and a parameter holding an array can only be a whole config node, never one item of a yaml sequence next to the site's own origins - which is the only way this is ever used. PHP callers read VideoPlatform::allCspOrigins() directly
        // A parameter rather than a prepended nelmio_security config: the app owns its policy, and a bundle silently widening someone's frame-src is exactly what a security header exists to prevent
        $containerBuilder->setParameter('c975l_ui.video.embed_origins', implode(' ', VideoPlatform::allCspOrigins()));

        // symfony/maker-bundle is dev-only in a consuming app - only wire MakeBlockCommand as a service when it's actually installed, instead of requiring it unconditionally
        if (class_exists(\Symfony\Bundle\MakerBundle\Maker\AbstractMaker::class)) {
            $containerConfigurator->import('../config/services_maker.yaml');
        }
    }

    #[\Override]
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
