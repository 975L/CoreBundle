<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Controller\Management\LegalModelController;
use c975L\UiBundle\Entity\Block;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// A legal_model block's row in its owner's form only holds the model it points at: what an editor reaching it
// from the document itself means to change is its wording, which lives on its own customization screen (see
// LegalModelController). Every BlockEditUrlProviderInterface implementation asks this before falling back on
// BlockFocusUrl, so the "Edit this block" button behaves the same whatever entity carries the document.
//
// Null for anything else, including a block whose model isn't one of ours - that one would 404 on the
// customization screen, so its owner's own form stays the right place to fix it.
class LegalModelEditUrl
{
    public static function build(UrlGeneratorInterface $urlGenerator, LegalModelCatalog $catalog, Block $block): ?string
    {
        if ('legal_model' !== $block->getKind() || !$catalog->has((string) ($block->getData()['model'] ?? ''))) {
            return null;
        }

        return $urlGenerator->generate(LegalModelController::CUSTOMIZE_ROUTE, ['block' => $block->getId()]);
    }
}
