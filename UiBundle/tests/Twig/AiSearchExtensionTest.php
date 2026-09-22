<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\AiSiteSearch;
use c975L\UiBundle\Service\AiSiteSearchClient;
use c975L\UiBundle\Twig\AiSearchExtension;
use PHPUnit\Framework\TestCase;

class AiSearchExtensionTest extends TestCase
{
    // The block needs an index to answer from, the privacy policy only the config its cache can be invalidated on
    public function testEnabledReadsTheSearchAndConfiguredReadsTheClient(): void
    {
        $search = $this->createStub(AiSiteSearch::class);
        $search->method('isEnabled')->willReturn(false);
        $client = $this->createStub(AiSiteSearchClient::class);
        $client->method('isEnabled')->willReturn(true);

        $functions = $this->functions($search, $client, $this->createStub(ConfigServiceInterface::class));

        $this->assertSame(['ai_search_enabled', 'ai_search_configured', 'ai_search_label'], array_keys($functions));
        $this->assertFalse($functions['ai_search_enabled']());
        $this->assertTrue($functions['ai_search_configured']());
    }

    // The name a site gave its assistant, trimmed; empty when it named none, the template then writing the translated default
    public function testLabelReadsTheConfigEntry(): void
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturnMap([
            ['ui-ai-assistant-site-label', '  Aide en ligne  '],
        ]);

        $functions = $this->functions($this->createStub(AiSiteSearch::class), $this->createStub(AiSiteSearchClient::class), $config);

        $this->assertSame('Aide en ligne', $functions['ai_search_label']());
    }

    public function testLabelIsEmptyWhenTheSiteNamedNone(): void
    {
        $functions = $this->functions($this->createStub(AiSiteSearch::class), $this->createStub(AiSiteSearchClient::class), $this->createStub(ConfigServiceInterface::class));

        $this->assertSame('', $functions['ai_search_label']());
    }

    /** @return array<string, callable> */
    private function functions(AiSiteSearch $search, AiSiteSearchClient $client, ConfigServiceInterface $config): array
    {
        $functions = [];
        foreach (new AiSearchExtension($search, $client, $config)->getFunctions() as $function) {
            $functions[$function->getName()] = $function->getCallable();
        }

        return $functions;
    }
}
