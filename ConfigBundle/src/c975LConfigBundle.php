<?php

/*
 * (c) 2018: 975L <contact@975l.com>
 * (c) 2018: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\DependencyInjection\Compiler\DeclaredUrlsHealthCheckPass;
use c975L\ConfigBundle\DependencyInjection\Compiler\TaggedInterfacePass;
use c975L\ConfigBundle\EventSubscriber\CspNonceCookieSubscriber;
use c975L\ConfigBundle\Management\AlertProviderInterface;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;
use c975L\ConfigBundle\Management\ContentOffenceLocatorInterface;
use c975L\ConfigBundle\Management\DashboardWidgetProviderInterface;
use c975L\ConfigBundle\Management\DevProfilePathProviderInterface;
use c975L\ConfigBundle\Management\EssentialActionProviderInterface;
use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\ConfigBundle\Management\HealthCheckAdviceProviderInterface;
use c975L\ConfigBundle\Management\HealthCheckProviderInterface;
use c975L\ConfigBundle\Management\ImportmapProviderInterface;
use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\ConfigBundle\Management\LinkableRouteProviderInterface;
use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\ConfigBundle\Management\ProcedureProviderInterface;
use c975L\ConfigBundle\Management\ShortcutProviderInterface;
use c975L\ConfigBundle\Management\SitemapProviderInterface;
use c975L\ConfigBundle\Management\StatusProviderInterface;
use c975L\ConfigBundle\Management\UrlMetadataProviderInterface;
use c975L\ConfigBundle\Management\WhatsNewProviderInterface;
use c975L\ConfigBundle\Scheduler\MaintenanceTaskProviderInterface;
use c975L\ConfigBundle\Security\OAuthLoginProviderInterface;
use Nelmio\SecurityBundle\ContentSecurityPolicy\NonceGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class c975LConfigBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $containerConfigurator, ContainerBuilder $container): void
    {
        // First what ConfigBundle asks of the framework itself, none of which the 18 sites installing it should have to declare
        $container->prependExtensionConfig('framework', [
            // The JS/CSS it ships (the onboarding tour, see assets/controllers-admin.js) - mirrors UiBundle's own asset_mapper path registration
            'asset_mapper' => [
                'paths' => [
                    __DIR__ . '/../assets' => '@c975l/config-bundle',
                ],
            ],
            // The store the front limiter counts in, pinned to the filesystem rather than left to cache.app: sparing the database a connection under a burst is the entire reason this exists, so a site whose application cache runs on PDO must not drag the counter there with it. Declared as a pool and not built by hand, which is what makes it a real cache.pool - visited by cache:pool:prune, where a hand-built adapter accumulates expired counter files nobody ever removes. It lives under the cache directory and is therefore emptied by cache:clear: harmless for a ten-second window, a deployment at worst letting one burst through
            'cache' => [
                'pools' => [
                    'c975l.rate_limiter' => [
                        'adapter' => 'cache.adapter.filesystem',
                    ],
                ],
            ],
            // 60 requests per 10 seconds, sliding: a page and the handful of dynamic sub-requests it pulls stay well under, while a catalogue scraper - the one measured ran at 13 req/s - is cut at its sixtieth. A per-minute ceiling loose enough for a human never catches a burst that is over in 16 seconds. Declared here rather than asked of the 18 sites installing the bundle, exactly as UiBundle declares its own; an application naming "c975l_front_request" itself still decides, its config being merged over this one
            'rate_limiter' => [
                'c975l_front_request' => [
                    'policy' => 'sliding_window',
                    'limit' => 60,
                    'interval' => '10 seconds',
                    'cache_pool' => 'c975l.rate_limiter',
                ],
            ],
        ]);

        // Then the user entity every c975L bundle relates to: they map against Contract\UserInterface, Doctrine resolves it to the application's own App\Entity\User. Guarded because a bundle checkout running its own tests has no DoctrineBundle registered
        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'resolve_target_entities' => [
                        UserInterface::class => \App\Entity\User::class,
                    ],
                ],
            ]);
        }

        // The CSP nonce cookie, signed so only a value this server issued is ever read back: an unsigned nonce is a nonce an injected script can carry (see CookieNonceGenerator). Both names, the secure one carrying the "__Host-" prefix and this config being unable to depend on the request. hash_algo stated rather than left to the default, which nelmio/security-bundle deprecates since 3.4 and changes in 4.0 - sha256 is that current default, so cookies already signed stay valid. Prepended rather than left to each site, the cookie being the bundle's own
        if ($container->hasExtension('nelmio_security')) {
            $container->prependExtensionConfig('nelmio_security', [
                'signed_cookie' => [
                    'names' => [CspNonceCookieSubscriber::COOKIE_NAME, CspNonceCookieSubscriber::COOKIE_NAME_SECURE],
                    'hash_algo' => 'sha256',
                ],
            ]);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TaggedInterfacePass(MenuProviderInterface::class, 'c975l.management_menu_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(ProcedureProviderInterface::class, 'c975l.procedure_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(WhatsNewProviderInterface::class, 'c975l.whatsnew_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(AlertProviderInterface::class, 'c975l.alert_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(ShortcutProviderInterface::class, 'c975l.shortcut_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(ImportProviderInterface::class, 'c975l.import_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(ExportProviderInterface::class, 'c975l.export_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(LinkableRouteProviderInterface::class, 'c975l.linkable_route_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(EssentialActionProviderInterface::class, 'c975l.essential_action_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(GuidedProjectProviderInterface::class, 'c975l.guided_project_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(DashboardWidgetProviderInterface::class, 'c975l.dashboard_widget_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(HealthCheckProviderInterface::class, 'c975l.health_check_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(HealthCheckAdviceProviderInterface::class, 'c975l.health_check_advice_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(ImportmapProviderInterface::class, 'c975l.importmap_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(SitemapProviderInterface::class, 'c975l.sitemap_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(UrlMetadataProviderInterface::class, 'c975l.url_metadata_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(ContentOffenceLocatorInterface::class, 'c975l.content_offence_locator'));
        $container->addCompilerPass(new TaggedInterfacePass(StatusProviderInterface::class, 'c975l.status_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(MaintenanceTaskProviderInterface::class, 'c975l.maintenance_task_provider'));
        $container->addCompilerPass(new TaggedInterfacePass(BackupPathProviderInterface::class, 'c975l.backup_path_provider'));
        // Collected by OAuthLoginProviderRegistry: a "sign in with X" shipped by another bundle or by an application is enabled by existing, with no list to edit here
        $container->addCompilerPass(new TaggedInterfacePass(OAuthLoginProviderInterface::class, 'c975l.oauth_login_provider'));
        // Only ever has anything to collect in dev, every implementation being marked #[When('dev')] - the pass itself stays registered in every environment, it simply tags nothing in prod
        $container->addCompilerPass(new TaggedInterfacePass(DevProfilePathProviderInterface::class, 'c975l.dev_profile_path_provider'));

        // Not a TaggedInterfacePass: this one builds one health-check service per sitemap provider found, rather than tagging services that already exist
        $container->addCompilerPass(new DeclaredUrlsHealthCheckPass());
    }

    public function loadExtension(array $config, ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void
    {
        $containerConfigurator->import('../config/services.yaml');

        $this->declareLocalesPattern($containerBuilder);

        // CookieNonceGenerator implements a NelmioSecurityBundle interface, so its class isn't loadable without that bundle. The package requires it now (UiBundle's layout calls csp_nonce()), the guard staying as the cheap way not to depend on that being true forever
        if (interface_exists(NonceGeneratorInterface::class)) {
            $containerConfigurator->import('../config/services_nelmio.yaml');
        }
    }

    // What a localised url accepts between its slashes: "en|es" on a site declaring these beside the one it is written in, the writing language left out so it keeps the bare urls the sitemaps and the hreflang groups declare, and nothing left - a single-language site - giving a pattern matching nothing, so the localised routes exist without ever answering. Declared here rather than by each bundle owning such routes: SiteBundle had it first, and ShopBundle, which requires this bundle and not that one, needs the very same string
    private function declareLocalesPattern(ContainerBuilder $containerBuilder): void
    {
        $locales = $containerBuilder->hasParameter('kernel.enabled_locales') ? (array) $containerBuilder->getParameter('kernel.enabled_locales') : [];
        $defaultLocale = $containerBuilder->hasParameter('kernel.default_locale') ? (string) $containerBuilder->getParameter('kernel.default_locale') : '';
        $locales = array_values(array_filter(array_map(strval(...), $locales), static fn (string $locale) => $locale !== $defaultLocale));

        $containerBuilder->setParameter(
            'c975l_config.locales_pattern',
            [] === $locales ? '(?!)' : implode('|', $locales),
        );
    }

    #[\Override]
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
