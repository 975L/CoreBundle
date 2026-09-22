<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\AiSiteSearch;
use c975L\UiBundle\Service\AiSiteSearchClient;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

// Whether the "ai_search" block draws its field (read at render time, the block isn't cached), and whether the privacy policy describes the search - on its config alone, the only thing LegalPlaceholderCacheListener can invalidate a cached legal model on
class AiSearchExtension extends AbstractExtension
{
    public function __construct(
        private readonly AiSiteSearch $aiSiteSearch,
        private readonly AiSiteSearchClient $aiSiteSearchClient,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ai_search_enabled', $this->aiSiteSearch->isEnabled(...)),
            new TwigFunction('ai_search_configured', $this->aiSiteSearchClient->isEnabled(...)),
            new TwigFunction('ai_search_label', $this->label(...)),
        ];
    }

    // The name the badge carries, a site naming its assistant itself: "Donovan" is 975L's own, which a client's site has no reason to show. Empty, the template writes the translated default
    private function label(): string
    {
        return trim((string) $this->configService->get('ui-ai-assistant-site-label'));
    }
}
