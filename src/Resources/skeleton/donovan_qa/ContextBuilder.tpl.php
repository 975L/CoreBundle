<?= "<?php\n" ?>
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace <?= $namespace ?>;

use c975L\UiBundle\Registry\BlockRegistry;

// One prompt section per registered block kind, so the model cites real kinds instead of hallucinating one
class <?= $class_name ?>
{
    public function __construct(
        private readonly BlockRegistry $blockRegistry,
    ) {
    }

    public function context(): string
    {
        $sections = [];
        foreach (array_keys($this->blockRegistry->all()) as $kind) {
            $label = $this->blockRegistry->getLabel($kind);
            $description = $this->blockRegistry->getDescription($kind);
            $sections[] = "### {$kind}\nLabel: {$label}\nDescription: {$description}";
        }

        return implode("\n\n", $sections);
    }

    // @param string[] $kinds
    // @return array{label: string, url: string}[]
    public function resolveSources(array $kinds): array
    {
        $sources = [];
        foreach ($kinds as $kind) {
            if (!$this->blockRegistry->has($kind)) {
                continue;
            }

            $sources[] = [
                'label' => $this->blockRegistry->getLabel($kind),
                // TODO: point this at your own block gallery, if you have one
                'url' => '',
            ];
        }

        return $sources;
    }
}
