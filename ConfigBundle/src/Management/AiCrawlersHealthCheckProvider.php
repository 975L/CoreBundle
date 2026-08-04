<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Attribute\AsHealthCheck;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Reports the AI crawlers that appeared in the community list since this site last updated its own (see AiCrawlerListUpdater) - the one thing about robots.txt that goes stale on its own, nothing in the site itself changing meanwhile. Monthly: that list gains a handful of names every few months, and a weekly call to a third party for it would be noise
#[AsHealthCheck(frequency: AsHealthCheck::FREQUENCY_MONTHLY)]
class AiCrawlersHealthCheckProvider implements HealthCheckProviderInterface
{
    // Kind this provider reports under, shared with HealthCheckController::SITE_WIDE_KINDS - this check reads a third party rather than a page of the site, so its row belongs to the "Site" section
    public const KIND = 'ai-crawlers';

    // Named in the warning rather than counted alone, so the dashboard says what would be blocked without having to run anything - beyond that the row would be unreadable, and the command prints the full list
    private const NAMED_IN_SUMMARY = 5;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly AiCrawlerListUpdater $aiCrawlerListUpdater,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    public function runChecks(): array
    {
        // A site that doesn't block them has no use for the list, and nothing to be told about it
        if (!$this->configService->get('seo-robots-block-ai')) {
            return [];
        }

        try {
            $comparison = $this->aiCrawlerListUpdater->compare();
        } catch (\Throwable $e) {
            return [$this->row(null, HealthCheckResult::STATUS_ERROR, 'label.health_check_ai_crawlers_call_failed', ['%message%' => $e->getMessage()], ['error' => $e->getMessage()])];
        }

        // No source configured is a deliberate opt-out, not something to report every month
        if (null === $comparison['source']) {
            return [];
        }

        if ([] === $comparison['missing']) {
            return [$this->row($comparison['source'], HealthCheckResult::STATUS_OK, 'label.health_check_ai_crawlers_ok')];
        }

        $named = \array_slice($comparison['missing'], 0, self::NAMED_IN_SUMMARY);

        return [$this->row(
            $comparison['source'],
            HealthCheckResult::STATUS_WARNING,
            'label.health_check_ai_crawlers_outdated',
            ['%count%' => \count($comparison['missing']), '%agents%' => implode(', ', $named)],
            ['missing' => $comparison['missing'], 'answerEngines' => $comparison['answerEngines']]
        )];
    }

    private function row(?string $url, string $status, string $translationId, array $parameters = [], array $details = []): array
    {
        return [
            // The list's own url, so the row links to what it is about - the site itself is never called here, this check reading a third party rather than a page
            'url' => $url ?? '',
            'label' => 'seo-robots-ai-crawlers',
            'status' => $status,
            'summary' => $this->translator->trans($translationId, $parameters, 'config'),
            'details' => $details,
        ];
    }
}
