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
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

// Form theme blocks only get "form"/"attr", never a service, hence this extension
class AiRephraseExtension extends AbstractExtension
{
    public function __construct(
        private readonly AiRephraseClient $aiRephraseClient,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ai_rephrase_enabled', [$this->aiRephraseClient, 'isEnabled']),
            new TwigFunction('ai_rephrase_styles', [$this->aiRephraseClient, 'getStyles']),
            new TwigFunction('ai_rephrase_lengths', [$this->aiRephraseClient, 'getLengths']),
            new TwigFunction('ai_assistant_name', [$this, 'assistantName']),
        ];
    }

    public function assistantName(): string
    {
        return 'Donovan';
    }
}
