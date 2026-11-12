<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Ai;

// Infomaniak's Euria (AI Tools), OpenAI-compatible: it differs by the base URI its entry carries, by its ceiling, the classic "max_tokens", and by sending no reasoning effort
class EuriaProvider extends OpenAiProvider
{
    #[\Override]
    public function getSlug(): string
    {
        return 'euria';
    }

    // Euria's models take no reasoning effort
    #[\Override]
    protected function lowestReasoningEffort(string $model): ?string
    {
        return null;
    }

    #[\Override]
    protected function ceilingParameter(): string
    {
        return 'max_tokens';
    }
}
