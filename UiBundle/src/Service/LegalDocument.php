<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\PdfGeneratorInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockLocationRegistry;
use c975L\UiBundle\Repository\BlockRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

// The site's own copy of a legal model (the "legal_model" block, else the model as shipped), its PDF cached under the kernel's cache directory and keyed by a hash of the rendered HTML
class LegalDocument
{
    private const string BLOCK_KIND = 'legal_model';

    /** @var array<string, string> */
    private array $html = [];

    public function __construct(
        private readonly LegalModelRenderer $renderer,
        private readonly LegalModelPlaceholders $placeholders,
        private readonly BlockRepository $blockRepository,
        private readonly PdfGeneratorInterface $pdfGenerator,
        private readonly Environment $twig,
        private readonly BlockLocationRegistry $blockLocationRegistry,
        #[Autowire(param: 'kernel.cache_dir')]
        private readonly string $cacheDir,
    ) {
    }

    // The document as the site publishes it, customization applied when a block carries one
    public function html(string $model, string $locale): string
    {
        return $this->html[$model . '|' . $locale] ??= $this->render($model, $locale);
    }

    // What the document is worth as a key: it moves whenever a single character of the document does
    public function fingerprint(string $model, string $locale): string
    {
        return substr(hash('xxh128', $this->html($model, $locale)), 0, 16);
    }

    /**
     * The document as a file, drawn once and kept until its text changes.
     *
     * Under "var/", not "public/": a deployment wipes it and the next visitor pays for one render, where a file
     * left in the document root would be served after the text under it had moved on.
     */
    public function pdf(string $model, string $locale, string $template = '@c975LUi/legal/pdf.html.twig'): string
    {
        $file = $this->cacheFile($model, $locale);

        if (is_file($file)) {
            return (string) file_get_contents($file);
        }

        $pdf = $this->pdfGenerator->renderHtml($this->twig->render($template, [
            'model' => $model,
            'locale' => $locale,
            'html' => $this->html($model, $locale),
        ]));

        $this->write($file, $pdf);

        return $pdf;
    }

    // Where that file lives, named after what it holds
    public function cacheFile(string $model, string $locale): string
    {
        return sprintf('%s/pdf/%s-%s-%s.pdf', $this->cacheDir, str_replace('/', '-', $model), $locale, $this->fingerprint($model, $locale));
    }

    private function render(string $model, string $locale): string
    {
        $block = $this->block($model);

        if (null === $block) {
            return $this->placeholders->substitute($this->renderer->render($model, null, [], $locale));
        }

        $data = $block->getData();

        return $this->renderer->render($model, $data['latestUpdate'] ?? null, (array) ($data['customization'] ?? []), $locale);
    }

    // The block the site publishes that model with: a hidden one, or one on a page unpublished or in the trash, is not the text a customer reads - among the rest the oldest, stable between two requests; a block no provider locates (no page management installed) is kept, having no page to be unpublished
    private function block(string $model): ?Block
    {
        $candidates = array_values(array_filter(
            $this->blockRepository->findByKind(self::BLOCK_KIND),
            static fn (Block $block): bool => !$block->isHidden() && ($block->getData()['model'] ?? null) === $model,
        ));
        if ([] === $candidates) {
            return null;
        }

        // Located through its top-level block: a page locates the blocks it holds directly, not the ones nested in a container
        $roots = array_map(self::root(...), $candidates);
        $locations = $this->blockLocationRegistry->getLocations($roots);
        foreach ($candidates as $index => $block) {
            if ($locations[(int) $roots[$index]->getId()]['published'] ?? false) {
                return $block;
            }
        }

        // Only when nothing locates any of them: a block placed on a page that is not published never stands in for it
        return [] === $locations ? $candidates[0] : null;
    }

    // The block a page holds directly, the one given when it is not nested
    private static function root(Block $block): Block
    {
        while (null !== $block->getParentBlock()) {
            $block = $block->getParentBlock();
        }

        return $block;
    }

    // The previous renders of that same document go with it: the directory holds one file per document, not one per revision - what was sent is in the customer's mailbox, not here
    private function write(string $file, string $pdf): void
    {
        $directory = \dirname($file);

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            return;
        }

        foreach (glob(preg_replace('/-[0-9a-f]{16}\.pdf$/', '-*.pdf', $file) ?? '') ?: [] as $stale) {
            @unlink($stale);
        }

        // Written aside then renamed: a confirmation sent meanwhile finds either no file or the whole of it, never a truncated PDF
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (false === file_put_contents($temporary, $pdf) || !rename($temporary, $file)) {
            @unlink($temporary);
        }
    }
}
