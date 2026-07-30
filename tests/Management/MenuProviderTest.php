<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Management\MenuProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuProviderTest extends TestCase
{
    private function createConfigService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([
            ['site-role-admin', 'ROLE_ADMIN'],
        ]);

        return $configService;
    }

    private function createTranslator(string $suffix = 'AI Agent'): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap([
            ['label.ai_assistant_menu_suffix', [], 'ui', $suffix],
        ]);

        return $translator;
    }

    // Matches ConfigBundle's/SiteBundle's section value so a future CRUD entry here merges into the same group
    public function testGetMenuSectionMatchesTheSharedManagementSection(): void
    {
        $provider = new MenuProvider($this->createConfigService(), $this->createTranslator());

        $this->assertSame(['label' => 'label.management', 'translation_domain' => 'site'], $provider->getMenuSection());
    }

    // UiBundle's own CRUD entries (Media Library) stay declared from SiteBundle - see its MenuProvider
    public function testGetMenusReturnsNoCrudEntries(): void
    {
        $provider = new MenuProvider($this->createConfigService(), $this->createTranslator());

        $this->assertSame([], $provider->getMenus());
    }

    // Fixed external url, not a route name, since every app's dashboard links to the same external block showcase site
    public function testGetLinksReturnsTheBlockShowcaseLink(): void
    {
        $provider = new MenuProvider($this->createConfigService(), $this->createTranslator());

        $links = $provider->getLinks();

        $this->assertCount(2, $links);
        $this->assertSame('label.block_showcase', $links['block_showcase']['label']);
        $this->assertSame('ui', $links['block_showcase']['translation_domain']);
        $this->assertSame('https://975l.com/pages/blocks', $links['block_showcase']['url']);
        $this->assertSame('_blank', $links['block_showcase']['target']);
        $this->assertSame('label.block_showcase_help', $links['block_showcase']['description']);
    }

    // 'role' matches the page's own minimum bar, a plain editor being unable to act on either section
    public function testGetLinksReturnsTheAiAssistantLinkWithTheHardcodedNameTranslatedSuffixAndRole(): void
    {
        $provider = new MenuProvider($this->createConfigService(), $this->createTranslator('AI Agent'));

        $links = $provider->getLinks();

        $this->assertSame('Donovan (AI Agent)', $links['ai_assistant']['label']);
        $this->assertSame('ui', $links['ai_assistant']['translation_domain']);
        $this->assertSame('management_ui_ai_assistant_index', $links['ai_assistant']['name']);
        $this->assertSame('ROLE_ADMIN', $links['ai_assistant']['role']);
        $this->assertSame('label.ai_assistant_subtitle', $links['ai_assistant']['description']);
    }
}
