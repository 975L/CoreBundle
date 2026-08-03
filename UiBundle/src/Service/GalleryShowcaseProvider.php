<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\GalleryShowcaseProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Twig\BlockExtension;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

// Shows the three built-in kinds no BlockFixtureProvider entry can express in a block showcase (see GalleryShowcaseRegistry): "flex_columns" and "section_cards" hold their content in Block::$slots, a relation rather than the plain data array a fixture is, and "collection" pulls its items live from a CollectionSourceRegistry no showcase has. Rendered here instead with never-persisted blocks - the same pipeline a real block goes through, just fed in memory. Each one sets "kind", which suppresses the empty preview card the showcase would otherwise draw for it.
class GalleryShowcaseProvider implements GalleryShowcaseProviderInterface
{
    // The same shape CollectionRuntime::renderItems() builds from a source's own CollectionItem, so a preview item renders exactly as a real one
    private const COLLECTION_ITEMS = [
        ['title' => 'Première entrée', 'content' => 'La description courte que la source renvoie pour cette entrée.'],
        ['title' => 'Deuxième entrée', 'content' => 'Chaque entrée est rendue comme un block, jamais enregistré.'],
        ['title' => 'Troisième entrée', 'content' => 'La source décide du nombre d\'entrées, la limite du block les tronque.'],
    ];

    // PlaceholderMediaRegistry optional for the same reason as BlockFixtureProvider's own - only the portfolio variant uses an image, and the bundle ships none itself
    public function __construct(
        private readonly BlockExtension $blockExtension,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly ?PlaceholderMediaRegistry $placeholderMedia = null,
    ) {
    }

    public function getShowcases(): array
    {
        return [
            $this->translator->trans('label.gallery_showcase_flex_columns', [], 'ui') => [
                'description' => $this->translator->trans('label.gallery_showcase_flex_columns_description', [], 'ui'),
                'kind' => 'flex_columns',
                'variants' => $this->flexColumnsVariants(),
            ],
            $this->translator->trans('label.gallery_showcase_section_cards', [], 'ui') => [
                'description' => $this->translator->trans('label.gallery_showcase_section_cards_description', [], 'ui'),
                'kind' => 'section_cards',
                'variants' => $this->sectionCardsVariants(),
            ],
            $this->translator->trans('label.gallery_showcase_collection', [], 'ui') => [
                'description' => $this->translator->trans('label.gallery_showcase_collection_description', [], 'ui'),
                'kind' => 'collection',
                'variants' => $this->collectionVariants(),
            ],
        ];
    }

    // Two width splits rather than one row: the twelfths carried by each column are the whole point of this kind, and a single balanced row would never show them at work
    private function flexColumnsVariants(): array
    {
        return [
            'Deux colonnes égales' => $this->renderFlexColumns('Deux colonnes de même largeur', [
                ['6', $this->textSlot('Première colonne', '<p>Chaque colonne groupe les blocks de son choix : texte, image, carte, bouton...</p>')],
                ['6', $this->textSlot('Deuxième colonne', '<p>Les deux colonnes se répartissent la largeur, six douzièmes chacune.</p>')],
            ]),
            'Colonnes 8 / 4' => $this->renderFlexColumns('Une colonne large, une étroite', [
                ['8', $this->textSlot('Le corps du propos', '<p>Huit douzièmes pour le texte principal, qui a besoin de la place.</p>')],
                ['4', $this->cardSlot('En complément', '<p>Quatre douzièmes pour ce qui l\'accompagne.</p>')],
            ]),
        ];
    }

    // A single row: what this kind adds to bare consecutive "card" blocks is the section head and the anchor, not a layout that varies
    private function sectionCardsVariants(): array
    {
        $section = (new Block())->setKind('section_cards')->setData([
            'eyebrow' => 'Surtitre de la section',
            'title' => 'Une rangée de cartes sous un titre de section',
        ]);

        foreach (['Première carte', 'Deuxième carte', 'Troisième carte'] as $position => $title) {
            $section->addSlot(
                $this->cardSlot($title, '<p>Une carte complète, la même que placée seule dans le flux de la page.</p>')
                    ->setPosition($position)
            );
        }

        return ['' => $this->blockExtension->renderBlock($section)];
    }

    // Both looks the "variant" field offers, the portfolio one borrowing PortfolioGrid's own grid and head (see Collection/Grid.html.twig)
    private function collectionVariants(): array
    {
        return [
            'Cartes' => $this->renderCollection(null),
            'Portfolio' => $this->renderCollection('portfolio'),
        ];
    }

    // Rendered straight through the Grid component: the block's own template would call collection_render_items(), i.e. query a source that a showcase has no reason to have. The items themselves are real "collection_item" blocks all the same, so what the grid holds is what a real collection holds
    private function renderCollection(?string $variant): string
    {
        $items = [];
        foreach (self::COLLECTION_ITEMS as $item) {
            $items[] = $this->blockExtension->renderBlock(
                (new Block())->setKind('collection_item')->setData($item + [
                    'url' => '',
                    'imageUrl' => 'portfolio' === $variant ? $this->placeholderImage() : '',
                    'buttonLabel' => '',
                    'buttonIcon' => '',
                    'detailUrl' => null,
                    'variant' => $variant,
                ])
            );
        }

        return $this->twig->render('@c975LUi/components/Collection/Grid.html.twig', [
            'eyebrow' => 'Surtitre de la collection',
            'title' => 'Les entrées viennent d\'une source, pas du block',
            'linkLabel' => '',
            'linkUrl' => '',
            'items' => $items,
            'variant' => $variant ?? '',
            'id' => '',
        ]);
    }

    // The section goes through BlockExtension::renderBlock() like any block: never persisted, it has no id, so nothing of this is cached (see renderBlock())
    private function renderFlexColumns(string $title, array $columns): string
    {
        $section = (new Block())->setKind('flex_columns')->setData([
            'eyebrow' => 'Colonnes flexibles',
            'title' => $title,
        ]);

        foreach ($columns as $position => [$width, $slot]) {
            $column = (new Block())->setKind('flex_column')->setPosition($position)->setData(['columnWidth' => $width]);
            $column->addSlot($slot);
            $section->addSlot($column);
        }

        return $this->blockExtension->renderBlock($section);
    }

    private function textSlot(string $title, string $content): Block
    {
        return (new Block())->setKind('text_section')->setData([
            'eyebrow' => '',
            'title' => $title,
            // Empty: the anchor a real editor would set has no use in a preview, and would collide with the page's own anchors
            'slug' => '',
            'content' => $content,
        ]);
    }

    private function cardSlot(string $title, string $content): Block
    {
        return (new Block())->setKind('card')->setData([
            'id' => '',
            'title' => $title,
            'level' => 'h3',
            'content' => $content,
            'url' => '',
            'target' => '',
            'buttonLabel' => '',
            'class' => [],
        ]);
    }

    // Leading "/" so the src is a site-root path whatever page the showcase is rendered on, the registry holding web paths without one - same as BlockFixtureProvider's own video embed. Nothing declared, no image: the cards then show their text alone rather than a broken one
    private function placeholderImage(): string
    {
        $images = $this->placeholderMedia?->getImages() ?? [];

        return [] === $images ? '' : '/' . reset($images);
    }
}
