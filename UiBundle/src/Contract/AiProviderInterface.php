<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

// One LLM API the AI features can call (Anthropic, OpenAI and Infomaniak's Euria ship with this bundle) - auto-tagged, so a site or a bundle adding one only implements it and lists its slug in the choices of a feature's "<prefix>-provider". It knows its protocol, never a feature: each feature hands it the key, base URI and model of its own entries (see AbstractAiProviderClient)
#[AutoconfigureTag('c975l.ai_provider')]
interface AiProviderInterface
{
    // Idle timeout: a model reading a photo takes longer before its first byte than one reading a sentence
    public const int TIMEOUT = 20;

    public const int TIMEOUT_WITH_IMAGES = 60;

    // What a feature's "<prefix>-provider" names it by ("anthropic")
    public function getSlug(): string;

    // The model's answer and the tokens it was billed, $images being data URIs ("data:image/jpeg;base64,...") read along with the prompt; "truncated" when the ceiling cut the answer, which the feature records as a failure. Throws on any transport or HTTP failure, which the feature logs and records
    /**
     * @param list<string>                                            $images
     * @param array{api-key: string, base-uri: string, model: string} $settings
     *
     * @return array{text: string, inputTokens: int, outputTokens: int, truncated?: bool}
     *
     * @throws ExceptionInterface
     */
    public function complete(string $prompt, ?string $system, int $maxTokens, array $images, array $settings): array;
}
