<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Controller\Management\UrlMetadataCrudController;
use c975L\ConfigBundle\Management\SocialMenuProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;

class SocialMenuProviderTest extends TestCase
{
    // MenuBuilder groups sections on "domain.label", so this pair has to be spelled exactly as SocialBundle spells its own or the back office draws two "Social" headers. Written as a literal rather than read from SocialBundle, which this bundle does not depend on and must not: it is the socle
    public function testTheSectionIsSpelledTheWaySocialBundleSpellsIt(): void
    {
        $this->assertSame(
            ['label' => 'label.social', 'translation_domain' => 'social'],
            $this->createProvider()->getMenuSection(),
        );
    }

    public function testItContributesTheUrlMetadataScreen(): void
    {
        $menus = $this->createProvider()->getMenus();

        $this->assertArrayHasKey('url_metadata', $menus);
        $this->assertSame(UrlMetadataCrudController::class, $menus['url_metadata']['controller']);
        $this->assertSame('label.url_metadata', $menus['url_metadata']['label']);
        $this->assertSame('config', $menus['url_metadata']['translation_domain']);
        // Same key as url_metadata_crud_index.html.twig's own explanatory text
        $this->assertSame('label.info_url_metadata', $menus['url_metadata']['description']);
        // The bar the screen itself sets: without it the entry would take the admin default and go missing from an editor's sidebar
        $this->assertSame('ROLE_EDITOR', $menus['url_metadata']['role']);
    }

    // No tier: the screen belongs in the section itself, an 'advanced' one being collected into the collapsed submenu instead and taken out of "Social" altogether
    public function testTheScreenStaysInItsSectionRatherThanInTheAdvancedSubmenu(): void
    {
        $this->assertArrayNotHasKey('tier', $this->createProvider()->getMenus()['url_metadata']);
    }

    public function testItContributesNoLink(): void
    {
        $this->assertSame([], $this->createProvider()->getLinks());
    }

    // Answers the editor key the entry names, the bar UrlMetadataCrudController sets on its own index
    private function createProvider(): SocialMenuProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key) => 'site-role-editor' === $key ? 'ROLE_EDITOR' : null
        );

        return new SocialMenuProvider($configService);
    }
}
