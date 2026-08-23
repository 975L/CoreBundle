<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\LegalDocument;
use c975L\UiBundle\Service\LegalModelCatalog;
use c975L\UiBundle\Service\LegalModelPlaceholders;
use c975L\UiBundle\Service\LegalModelRenderer;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

class LegalModelExtension
{
    public function __construct(
        private readonly LegalModelRenderer $renderer,
        private readonly LegalDocument $legalDocument,
        private readonly LegalModelPlaceholders $placeholders,
        private readonly RequestStack $requestStack,
    ) {
    }

    // The site's own data inside a model - resolved on the spot, or written as a %marker% while the customization screen is collecting the model's units (see LegalModelPlaceholders)
    #[AsTwigFunction('legal_var', isSafe: ['html'])]
    public function legalVar(string $slug): string
    {
        return $this->placeholders->value($slug);
    }

    // Renders a "legal_model" block: its model, in the request's locale, with its own customization applied
    #[AsTwigFunction('legal_model', isSafe: ['html'])]
    public function legalModel(Block $block): string
    {
        $data = $block->getData();

        return $this->legalModelHtml(
            (string) ($data['model'] ?? ''),
            $data['latestUpdate'] ?? null,
            (array) ($data['customization'] ?? []),
        );
    }

    // The site's own copy of a document, which is the only one a customer ever accepted: the "legal_model" block's version where the site rewrote it, the model as shipped otherwise. What every page, file and attachment carrying a legal document reads, so none of them can come to say something else (see LegalDocument)
    #[AsTwigFunction('legal_document_html', isSafe: ['html'])]
    public function legalDocumentHtml(string $model, ?string $locale = null): string
    {
        return $this->legalDocument->html(
            $model,
            $locale ?? $this->requestStack->getCurrentRequest()?->getLocale() ?? LegalModelCatalog::FALLBACK_LOCALE,
        );
    }

    // The same document straight from a model identifier, for an app rendering a legal page from its own template rather than from a block - what a site installing ShopBundle without any page management needs for its terms of sales. No customization screen goes with it, so whatever delta is passed here is the caller's own
    #[AsTwigFunction('legal_model_html', isSafe: ['html'])]
    public function legalModelHtml(string $model, ?string $latestUpdate = null, array $customization = [], ?string $locale = null): string
    {
        return $this->renderer->render(
            $model,
            $latestUpdate,
            $customization,
            $locale ?? $this->requestStack->getCurrentRequest()?->getLocale() ?? LegalModelCatalog::FALLBACK_LOCALE,
        );
    }
}
