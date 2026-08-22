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

    // The ink of a button is not a choice the admin makes, it is a reading of the colour behind it: what each theme colour hands to the tokens the label, the link and the icon are painted with (see sass/_tokens.scss)
    private const array DERIVED_INKS = [
        'theme-color-primary' => [
            'colors' => ['--c975l-button-color', '--c975l-button-link-color'],
            'invert' => '--c975l-button-icon-invert',
        ],
        'theme-color-secondary' => [
            'colors' => ['--c975l-button-secondary-color'],
            'invert' => '--c975l-button-secondary-icon-invert',
        ],
        // The dark ambience carries a palette of its own, and a site filling it with a pale blue - the usual choice, a deep brand blue reading badly on a dark page - lands exactly on the reading this exists for. Published beside the others rather than instead of them: SiteBundle's dark block is what picks these up, the same way it picks --c975l-color-primary-dark-mode up for --primary (see its sass/_theme-dark.scss)
        'theme-color-primary-dark-mode' => [
            'colors' => ['--c975l-button-color-dark-mode', '--c975l-button-link-color-dark-mode'],
            'invert' => '--c975l-button-icon-invert-dark-mode',
        ],
        'theme-color-secondary-dark-mode' => [
            'colors' => ['--c975l-button-secondary-color-dark-mode'],
            'invert' => '--c975l-button-secondary-icon-invert-dark-mode',
        ],
    ];

    // Where white stops being readable and black starts: the two contrast ratios meet at this relative luminance, so it is the one crossing point rather than a taste
    private const float INK_THRESHOLD = 0.179;

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
        $values = [];
        foreach ($this->configRepository->findBySlugPrefix(self::CSS_SLUG_PREFIX) as $config) {
            $values[$config->getSlug()] = $config->getValue();
            $line = $this->variableLine($config);
            if (null !== $line) {
                $lines[] = $line;
            }
        }

        // Appended after the loop, and not in variableLine(): that mapping is mechanical on purpose, and a colour read to write two other properties is exactly the lookup table it exists not to have
        $lines = [...$lines, ...$this->derivedInkLines($values)];

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

    /**
     * The ink each theme colour calls for, as the declarations the stylesheet's own defaults give way to.
     *
     * A site picking a light brand colour used to reach its visitors as white on light blue - 1.97:1, which is
     * unreadable and the first thing an accessibility audit reports - because the label kept the white the
     * library writes by default. The icon goes with it: an icon on a button is an <img> painted by an inversion
     * rather than by the colour of the label beside it, so a dark label with a white icon would be the same
     * button written twice.
     *
     * @param array<string, string|null> $values every theme config by its slug
     *
     * @return string[]
     */
    private function derivedInkLines(array $values): array
    {
        $lines = [];
        foreach (self::DERIVED_INKS as $slug => $tokens) {
            $luminance = $this->luminance($values[$slug] ?? null);

            // A colour nothing here reads - hsl(), a named one - keeps the default the stylesheet already carries rather than being guessed at
            if (null === $luminance) {
                continue;
            }

            $onLight = $luminance > self::INK_THRESHOLD;
            foreach ($tokens['colors'] as $name) {
                $lines[] = sprintf('    %s: %s;', $name, $onLight ? '#000' : '#fff');
            }
            $lines[] = sprintf('    %s: %d;', $tokens['invert'], $onLight ? 0 : 1);
        }

        return $lines;
    }

    // The relative luminance of a colour as WCAG defines it, or null for one this does not read. Hex and rgb() only: those are what the back-office colour picker writes, and a form nobody can produce there is not worth a parser
    private function luminance(?string $color): ?float
    {
        $channels = $this->rgb($color);
        if (null === $channels) {
            return null;
        }

        $linear = [];
        foreach ($channels as $channel) {
            $value = $channel / 255;
            $linear[] = $value <= 0.03928 ? $value / 12.92 : ((($value + 0.055) / 1.055) ** 2.4);
        }

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    /** @return array{int, int, int}|null */
    private function rgb(?string $color): ?array
    {
        $color = strtolower(trim((string) $color));

        if (1 === preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $color, $hex)) {
            $value = $hex[1];
            if (3 === strlen($value)) {
                $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
            }

            return [(int) hexdec(substr($value, 0, 2)), (int) hexdec(substr($value, 2, 2)), (int) hexdec(substr($value, 4, 2))];
        }

        if (1 === preg_match('/^rgba?\(\s*(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})/', $color, $parts)) {
            return [min(255, (int) $parts[1]), min(255, (int) $parts[2]), min(255, (int) $parts[3])];
        }

        return null;
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
