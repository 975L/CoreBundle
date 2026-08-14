<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\UiBundle\CacheWarmer\StylesheetCacheWarmer;
use c975L\UiBundle\Service\BuildFileWriter;
use c975L\UiBundle\Twig\FontPreloadExtension;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Contracts\Cache\CacheInterface;

// Regenerates the compiled theme CSS whenever a "theme-" Config is flushed, site_config being the single source of truth
// Also a CacheWarmer: rows restored from a backup fire no Doctrine event of their own
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class ThemeVariablesCssListener implements CacheWarmerInterface
{
    // What marks a config as a CSS value, wherever it is displayed: the slug, not the group - a satellite bundle declaring colors of its own (c975l/gallery-bundle's "theme-color-gallery-*") keeps them in its own back-office group rather than in SiteBundle's
    private const string CSS_SLUG_PREFIX = 'theme-';

    // Theme configs that are never a CSS value, so must stay out of the compiled :root block
    private const array EXCLUDED_SLUGS = ['theme-mode'];

    // Generic fallback per font slug, for when a chosen custom font fails to load at runtime
    private const array FONT_FALLBACKS = [
        'theme-font-family-title' => 'sans-serif',
        'theme-font-family-body' => 'sans-serif',
        'theme-font-family-accent' => 'monospace',
    ];

    // The per-entity events only raise this flag, the three stylesheets being recompiled once per flush - the back-office saves the whole theme group at once, which otherwise rebuilt site-theme.css + site.css + admin.css ten times over, inside the transaction. Same batching as FontCssListener
    private bool $stale = false;

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly StylesheetCacheWarmer $stylesheetCacheWarmer,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        private readonly CacheInterface $cache,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->markIfThemeConfig($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->markIfThemeConfig($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->markIfThemeConfig($args->getObject());
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->stale) {
            return;
        }

        $this->stale = false;
        $this->regenerate();
    }

    public function isOptional(): bool
    {
        return true;
    }

    // Same guard as FontCssListener::warmUp(), for the same reason: "site_config" is no more guaranteed to exist during the cache:clear a composer install runs than "site_font" is
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        try {
            $this->regenerate();
        } catch (DBALException) {
            return [];
        }

        return [];
    }

    private function markIfThemeConfig(object $entity): void
    {
        if ($entity instanceof Config && str_starts_with($entity->getSlug(), self::CSS_SLUG_PREFIX)) {
            $this->stale = true;
        }
    }

    // Rewrites the whole file from every current theme config, not just the one that changed
    private function regenerate(): void
    {
        // The <head>'s preloads are computed from the same rows, so they go stale at the same moment
        $this->cache->delete(FontPreloadExtension::CACHE_KEY);

        $lines = [];
        foreach ($this->configRepository->findBySlugPrefix(self::CSS_SLUG_PREFIX) as $config) {
            $line = $this->variableLine($config);
            if (null !== $line) {
                $lines[] = $line;
            }
        }

        BuildFileWriter::write($this->projectDir, 'site-theme.css', [] === $lines ? '' : ":root {\n" . implode("\n", $lines) . "\n}\n");

        // In prod, the real site never reads this file directly - it links UiBundle's concatenated bundles/build/site.css instead (see StylesheetExtension), which is otherwise only rebuilt on cache:warmup. Without this, an admin editing a "theme" config would regenerate site-theme.css but still see the previous theme until the next deploy/warmup
        $this->stylesheetCacheWarmer->compileAll();
    }

    // One config row as its ":root" custom property declaration, or null when the row isn't one - mechanical mapping, e.g. "theme-color-primary" -> "--c975l-color-primary": no lookup table to maintain when a new theme variable is added to a bundle's configs.json. Restates the prefix the query already filtered on, an excluded slug and an empty value being skipped here too
    private function variableLine(Config $config): ?string
    {
        $slug = $config->getSlug();
        $value = $config->getValue();
        if (null === $value || '' === $value || !str_starts_with($slug, self::CSS_SLUG_PREFIX) || in_array($slug, self::EXCLUDED_SLUGS, true)) {
            return null;
        }

        return sprintf('    --c975l-%s: %s;', substr($slug, strlen(self::CSS_SLUG_PREFIX)), $this->withFontFallback($slug, $value));
    }

    // A bare font name is quoted and gets its generic fallback appended; a value already holding a comma is a full stack the admin wrote, left alone
    // The quoting is what makes an uploaded family usable at all: unquoted, a name carrying a digit ("Cormorant Garamond latin 400") is not a valid <custom-ident> sequence, so every "font-family: var(--c975l-font-family-body)" it reaches is invalid at computed-value time and the text silently falls back to the browser's default
    private function withFontFallback(string $slug, string $value): string
    {
        $fallback = self::FONT_FALLBACKS[$slug] ?? null;
        if (null === $fallback || str_contains($value, ',') || in_array($value, Config::GENERIC_FONT_FAMILIES, true)) {
            return $value;
        }

        // An admin who quoted the name himself is normalised back to a bare one before it is quoted here, or "Roboto" typed with its quotes would be declared as "\"Roboto\"" - a family matching nothing, so the very fallback this quoting exists to avoid
        $value = trim($value);
        if (strlen($value) >= 2 && $value[0] === $value[strlen($value) - 1] && in_array($value[0], ['"', "'"], true)) {
            $value = substr($value, 1, -1);
        }

        return sprintf('"%s", %s', str_replace(['\\', '"'], ['\\\\', '\\"'], $value), $fallback);
    }
}
