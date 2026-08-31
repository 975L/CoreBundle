<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Service\AiRephraseClient;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\TranslationFormContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

// Form theme blocks only get "form"/"attr", never a service, hence this extension
class AiRephraseExtension extends AbstractExtension
{
    public function __construct(
        private readonly AiRephraseClient $aiRephraseClient,
        private readonly ContentTranslator $contentTranslator,
        private readonly TranslationFormContext $translationFormContext,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ai_rephrase_enabled', $this->aiRephraseClient->isEnabled(...)),
            new TwigFunction('ai_rephrase_styles', $this->aiRephraseClient->getStyles(...)),
            new TwigFunction('ai_rephrase_lengths', $this->aiRephraseClient->getLengths(...)),
            new TwigFunction('ai_assistant_name', $this->assistantName(...)),
            // The languages the same button may translate into, empty on a site declaring a single one - which is what hides the whole choice
            new TwigFunction('ai_translatable_locales', $this->contentTranslator->getTranslatableLocales(...)),
            // The one language a translation screen writes, which turns the choice above into a single button
            new TwigFunction('ai_translation_target', $this->translationFormContext->get(...)),
        ];
    }

    public function assistantName(): string
    {
        return 'Donovan';
    }
}
