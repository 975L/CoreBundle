<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\AiCrawlerListClient;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

// Keeps the "seo-robots-ai-crawlers" config in step with the community list at "seo-robots-ai-crawlers-source" (ai.robots.txt), which gains a handful of names every few months - a list nobody remembers to review is a robots.txt that quietly stops covering what it was written for. Never applies anything on its own: AiCrawlersHealthCheckProvider reports what appeared upstream, and the c975l:seo:crawlers:update command (or its dashboard tile) is what merges it in, because deciding what a site blocks is not a third party's call
class AiCrawlerListUpdater
{
    // Never merged in, whatever the upstream list says about them: these fetch a page to answer someone's question and cite it back, so blocking them costs visibility and gains nothing - they are also what reads the llms.txt a site publishes, and keeping them out is what lets "seo-robots-block-ai" ship on. Named in the generated robots.txt by SeoFilesWriter, and reported rather than silently dropped by an update, since a site is free to add one by hand
    public const ANSWER_ENGINES = [
        'Applebot',
        'Bingbot',
        'ChatGPT-User',
        'Claude-SearchBot',
        'Claude-User',
        'DuckAssistBot',
        'facebookexternalhit',
        'Googlebot',
        'meta-externalfetcher',
        'OAI-SearchBot',
        'Perplexity-User',
        'PerplexityBot',
        'YouBot',
    ];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly AiCrawlerListClient $aiCrawlerListClient,
        private readonly ConfigRepository $configRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // What the upstream list holds that this site's own doesn't. 'missing' is what the update would add, 'answerEngines' what it deliberately leaves out, 'source' the url read - null when the config is empty, which is how a site turns the whole thing off
    // @return array{missing: list<string>, answerEngines: list<string>, source: ?string}
    public function compare(): array
    {
        $source = trim((string) $this->configService->get('seo-robots-ai-crawlers-source'));
        if ('' === $source) {
            return ['missing' => [], 'answerEngines' => [], 'source' => null];
        }

        $current = $this->currentAgents();
        $missing = [];
        $answerEngines = [];

        foreach ($this->aiCrawlerListClient->fetch($source) as $agent) {
            if ($this->holds($current, $agent)) {
                continue;
            }

            if ($this->holds(self::ANSWER_ENGINES, $agent)) {
                $answerEngines[] = $agent;

                continue;
            }

            $missing[] = $agent;
        }

        return ['missing' => $missing, 'answerEngines' => $answerEngines, 'source' => $source];
    }

    // Adds the given agents to the config, and returns the list as it now stands. Additive on purpose: a name this site added by hand, or one upstream has since dropped, is never removed by an update it didn't ask for
    public function apply(array $agents): array
    {
        $updated = $this->currentAgents();
        foreach ($agents as $agent) {
            if (!$this->holds($updated, $agent)) {
                $updated[] = $agent;
            }
        }

        sort($updated, \SORT_FLAG_CASE | \SORT_STRING);

        $config = $this->configRepository->findOneBySlug('seo-robots-ai-crawlers');
        if (null === $config) {
            throw new \RuntimeException('The "seo-robots-ai-crawlers" config is missing, run c975l:config:load-all first.');
        }

        $config->setValue((string) json_encode($updated));
        $config->setModification(new \DateTime());
        $this->entityManager->persist($config);
        $this->entityManager->flush();

        // The generated robots.txt is built from the cached configs, so a list updated behind the cache would only take effect on the next clear
        $this->configService->invalidateCache();

        return $updated;
    }

    // @return list<string>
    private function currentAgents(): array
    {
        $agents = $this->configService->get('seo-robots-ai-crawlers');

        if (!is_array($agents)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $agent): string => is_string($agent) ? trim($agent) : '',
            $agents
        )));
    }

    // User agent tokens are matched case-insensitively by crawlers themselves, so "GPTBot" and "gptbot" are the same entry - adding the second because the site wrote the first in another case would grow the list forever
    private function holds(array $agents, string $agent): bool
    {
        foreach ($agents as $known) {
            if (0 === strcasecmp($known, $agent)) {
                return true;
            }
        }

        return false;
    }
}
