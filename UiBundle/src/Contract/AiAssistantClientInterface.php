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

    /**
     * Returns null when the feature is disabled/unconfigured, so callers can distinguish "no answer
     * available" from an empty string answer. "sources" is always present (possibly empty) - a backend
     * with no citation support of its own can simply omit it from its response, AiAssistantClient
     * defaults it to []. A source is a {label, url} pair, this bundle making no assumption about what
     * URL scheme a backend's own citations resolve to. The one exception is a source carrying
     * "project", a guided project's slug: that one names something this site holds itself, so it comes
     * with no url at all and is rendered as a button starting the parcours where the answer is read
     * (see assets/js/ai-assistant.js and ConfigBundle's assets/js/guided-project.js).
     *
     * @return array{answer: string, sources: array{label: string, url: string, project?: string}[]}|null
     */
    public function ask(string $question): ?array;
}
