<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

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

        $functions = [];
        foreach (new AiSearchExtension($search, $client)->getFunctions() as $function) {
            $functions[$function->getName()] = $function->getCallable();
        }

        $this->assertSame(['ai_search_enabled', 'ai_search_configured'], array_keys($functions));
        $this->assertFalse($functions['ai_search_enabled']());
        $this->assertTrue($functions['ai_search_configured']());
    }
}
