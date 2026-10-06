<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Asks a free-text question about the block system and returns an answer. Deliberately agnostic about what's behind it - the default implementation (AiAssistantClient) forwards to a plain HTTP endpoint read from config ("ui-ai-assistant-dashboard-endpoint"), left empty by default. This bundle ships no default endpoint and no default backend of any kind - a consuming app wanting the dashboard assistant points that config at whatever service it operates (or none, leaving the feature dark). Override this service (see Readme) to plug in something else entirely, e.g. a purely local implementation.
interface AiAssistantClientInterface
{
    // Whether ask() can actually answer right now - fully configured, not just switched on.
    public function isEnabled(): bool;

    // Null when disabled/unconfigured; "sources" always present, each a {label, url} pair or a {label, project} one naming a guided project's slug, rendered as a button starting it (see assets/js/ai-assistant.js). $locale is the reader's language, null leaving it to the backend
    /** @return array{answer: string, sources: array{label: string, url: string, project?: string}[]}|null */
    public function ask(string $question, ?string $locale = null): ?array;
}
