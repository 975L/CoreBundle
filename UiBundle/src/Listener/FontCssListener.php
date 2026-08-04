<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\CacheWarmer\StylesheetCacheWarmer;
use c975L\UiBundle\Entity\Font;
use c975L\UiBundle\Repository\FontRepository;
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

// Regenerates the compiled @font-face stylesheet from every uploaded Font, on flush and on cache warmup
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class FontCssListener implements CacheWarmerInterface
{
    // The per-entity events only raise this flag, the whole file being rewritten once per flush - a bulk import of twenty fonts would otherwise recompile every stylesheet twenty times, inside the transaction
    private bool $stale = false;

    public function __construct(
        private readonly FontRepository $fontRepository,
        private readonly StylesheetCacheWarmer $stylesheetCacheWarmer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly CacheInterface $cache,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->markIfFont($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->markIfFont($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->markIfFont($args->getObject());
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

    // A warmup legitimately runs before the database is ready: cache:clear is part of every composer install, so on a first install - and on any deploy that adds this bundle - "site_font" does not exist yet, the app's own migration not having run. CacheWarmerAggregate doesn't catch, so an unguarded query fails the deploy before migrations can even be reached. The file is written by the next warmup or the next Font flush instead. Only guarded here: the same failure on a flush is a real error
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        try {
            $this->regenerate();
        } catch (DBALException) {
            return [];
        }

        return [];
    }

    private function markIfFont(object $entity): void
    {
        if ($entity instanceof Font) {
            $this->stale = true;
        }
    }

    // Rewrites the whole file from every current Font row, not just the one that changed
    private function regenerate(): void
    {
        // The <head>'s preloads are computed from the same rows, so they go stale at the same moment
        $this->cache->delete(FontPreloadExtension::CACHE_KEY);

        $blocks = [];
        foreach ($this->fontRepository->findAllOrdered() as $font) {
            $rule = $this->fontFaceRule($font);
            if (null !== $rule) {
                $blocks[] = $rule;
            }
        }

        BuildFileWriter::write($this->projectDir, 'site-fonts-uploaded.css', implode("\n", $blocks));

        // In prod, the real site never reads this file directly - it links UiBundle's concatenated bundles/build/site.css instead (see StylesheetExtension), which is otherwise only rebuilt on cache:warmup. Without this, uploading a font would regenerate site-fonts-uploaded.css but the live site would keep serving the previous version until the next deploy/warmup
        $this->stylesheetCacheWarmer->compileAll();
    }

    // One Font row as its @font-face rule, or null for a row with nothing to serve yet (no uploaded file, unknown format)
    private function fontFaceRule(Font $font): ?string
    {
        $format = $font->getFormat();
        if (null === $font->getFilename() || null === $format) {
            return null;
        }

        return sprintf(
            "@font-face {\n    font-family: \"%s\";\n    src: url(\"/%s\") format(\"%s\");\n    font-weight: %s;\n    font-style: %s;\n    font-display: swap;\n}\n",
            str_replace('"', '\\"', $font->getName() ?? ''),
            $font->getFilename(),
            $format,
            // The full 100-900 span is always safe, the browser clamping to what the file supports
            $font->isVariable() ? '100 900' : (string) $font->getWeight(),
            $font->getStyle(),
        );
    }
}
