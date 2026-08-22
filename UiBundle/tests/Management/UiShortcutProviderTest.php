<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Management\ShortcutProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\BlockShortcutController;
use c975L\UiBundle\Controller\Management\EmailDebugShortcutController;
use c975L\UiBundle\Controller\Management\StylesheetShortcutController;
use c975L\UiBundle\Management\UiShortcutProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class UiShortcutProviderTest extends TestCase
{
    // Translator double that returns the translation key untouched, so labels stay assertable
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        return $translator;
    }

    // Config double answering the "email-debug" key alone, the only one this provider reads
    private function createConfigService(bool $emailDebugEnabled): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('getBool')->willReturn($emailDebugEnabled);

        return $configService;
    }

    public function testGetShortcutsReturnsBlockCacheClearShortcut(): void
    {
        $provider = new UiShortcutProvider($this->createTranslator(), $this->createConfigService(false));

        $shortcuts = $provider->getShortcuts();

        $this->assertCount(3, $shortcuts);
        $this->assertSame('label.block_clear_cache', $shortcuts[0]['label']);
        $this->assertSame(BlockShortcutController::CLEAR_CACHE_ROUTE, $shortcuts[0]['route']);
        $this->assertFalse($shortcuts[0]['active']);
        $this->assertSame('ROLE_SUPER_ADMIN', $shortcuts[0]['role']);
        $this->assertSame(ShortcutProviderInterface::CATEGORY_MAINTENANCE, $shortcuts[0]['category']);
    }

    public function testGetShortcutsReturnsStylesheetCompileShortcut(): void
    {
        $provider = new UiShortcutProvider($this->createTranslator(), $this->createConfigService(false));

        $shortcuts = $provider->getShortcuts();

        $this->assertSame('label.stylesheet_compile', $shortcuts[1]['label']);
        $this->assertSame(StylesheetShortcutController::COMPILE_ROUTE, $shortcuts[1]['route']);
        $this->assertFalse($shortcuts[1]['active']);
        $this->assertSame('ROLE_SUPER_ADMIN', $shortcuts[1]['role']);
        $this->assertSame(ShortcutProviderInterface::CATEGORY_MAINTENANCE, $shortcuts[1]['category']);
    }

    // Debug mode off: the tile offers to turn it on, and stays neutral - nothing is switched on to notice
    public function testGetShortcutsOffersToEnableTheEmailDebugWhenItIsOff(): void
    {
        $provider = new UiShortcutProvider($this->createTranslator(), $this->createConfigService(false));

        $shortcuts = $provider->getShortcuts();

        $this->assertSame('label.email_debug_enable', $shortcuts[2]['label']);
        $this->assertSame(EmailDebugShortcutController::TOGGLE_ROUTE, $shortcuts[2]['route']);
        $this->assertFalse($shortcuts[2]['active']);
        $this->assertSame('ROLE_SUPER_ADMIN', $shortcuts[2]['role']);
        $this->assertSame(ShortcutProviderInterface::CATEGORY_TOGGLE, $shortcuts[2]['category']);
    }

    // Debug mode on: no email leaves the site any more, which the warning-colored tile is there to say (see _shortcuts.html.twig)
    public function testGetShortcutsOffersToDisableTheEmailDebugWhenItIsOn(): void
    {
        $provider = new UiShortcutProvider($this->createTranslator(), $this->createConfigService(true));

        $shortcuts = $provider->getShortcuts();

        $this->assertSame('label.email_debug_disable', $shortcuts[2]['label']);
        $this->assertTrue($shortcuts[2]['active']);
    }
}
