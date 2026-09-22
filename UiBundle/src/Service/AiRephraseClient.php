<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\AiUsage;

// Rephrases - or translates - free text using the client's own key ("ui-ai-assistant-rephrase-*" config, distinct from the dashboard assistant's key) - stateless, nothing is ever persisted or logged beyond the request itself. The provider call itself is AbstractAiProviderClient's, shared with the site search. No interface here (unlike AiAssistantClient): there's nothing to override, a consuming app not wanting this feature simply leaves the api-key config empty. Token counts from each response are handed to AiUsageTracker - a numeric count alone reveals nothing about the rephrased content, so this doesn't compromise the "nothing is persisted" promise above
class AiRephraseClient extends AbstractAiProviderClient
{
    // Closed list: $style indexes this map, so a request parameter can never inject its own instructions
    private const array STYLES = [
        'neutral' => '',
        'professional' => ' Use a formal, professional tone.',
        'friendly' => ' Use a warm, friendly, conversational tone.',
        'concise' => ' Be as concise as possible while keeping the meaning intact.',
        'persuasive' => ' Use a persuasive, compelling tone that encourages action.',
        'simple' => ' Use simple words and short sentences, easy to understand for a broad audience.',
        'enthusiastic' => ' Use an enthusiastic, energetic tone.',
        'expanded' => ' Expand the text with more detail and context, while keeping the same meaning.',
    ];

    // Same closed list as STYLES; an explicit paragraph count, a relative "longer" being unpredictable
    private const array LENGTHS = [
        'same' => ' Keep approximately the same length.',
        'paragraph_1' => ' Rewrite it as exactly one paragraph.',
        'paragraph_2' => ' Rewrite it as exactly two paragraphs, separated by a blank line.',
        'paragraph_3' => ' Rewrite it as exactly three paragraphs, separated by a blank line.',
        'paragraph_4' => ' Rewrite it as exactly four paragraphs, separated by a blank line.',
        'social_summary' => ' Condense it into a single summary of at most 155 characters, meant to be used as a meta description and as the text of a social network share card: plain text on one line, no quotes, no hashtags, no emoji, no trailing ellipsis.',
    ];

    protected function configPrefix(): string
    {
        return 'ui-ai-assistant-rephrase';
    }

    protected function feature(): string
    {
        return AiUsage::FEATURE_REPHRASE;
    }

    // @return string[] Style keys accepted by rephrase(), for a caller building a selector
    public function getStyles(): array
    {
        return array_keys(self::STYLES);
    }

    // @return string[] Length keys accepted by rephrase(), for a caller building a selector
    public function getLengths(): array
    {
        return array_keys(self::LENGTHS);
    }

    public function rephrase(string $text, string $style = 'neutral', string $length = 'same'): ?string
    {
        $styleInstruction = self::STYLES[$style] ?? self::STYLES['neutral'];
        $lengthInstruction = self::LENGTHS[$length] ?? self::LENGTHS['same'];

        return $this->send(
            'Rephrase the following text, keeping its original language and meaning.'
            . $lengthInstruction
            . $styleInstruction
            . " Return only the rephrased text, nothing else:\n\n" . $text
        );
    }

    // The same key and budget as a rephrase, asked for by the same button, so an editor never reaches for a translator of their own
    // The locale's shape is checked rather than trusted, anything else writing the caller's own sentence into the prompt
    public function translate(string $text, string $locale): ?string
    {
        if (1 !== preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z]{2,4})?$/', $locale)) {
            return null;
        }

        return $this->send(
            sprintf('Translate the following text into the language whose IETF code is "%s".', $locale)
            . ' Keep its meaning, its tone and its formatting.'
            . " Return only the translated text, nothing else:\n\n" . $text
        );
    }
}
