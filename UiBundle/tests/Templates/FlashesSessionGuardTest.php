<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;

// Reading app.flashes starts a session, so the layout only reads it for a visitor already carrying the session cookie or for a request that started the session itself, which keeps anonymous pages cacheable
class FlashesSessionGuardTest extends TestCase
{
    public function testTheLayoutGuardsOnBothTermsBeforeReadingTheFlashes(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString(
            'ui_can_hold_flash()',
            $layout,
            'The layout no longer asks whether the visitor can hold a flash, so a session is opened again for every anonymous visitor and every crawler.'
        );
    }

    // The guard sits inside the block, not around it: a theme overriding the block takes the guard over with it
    public function testTheFlashesBlockStaysInsideTheGuard(): void
    {
        $layout = $this->layout();

        $block = strpos($layout, '{% block flashes %}');
        $this->assertNotFalse($block, 'The layout no longer prints the flashes block.');

        $guard = strpos($layout, 'ui_can_hold_flash()');
        $this->assertNotFalse($guard, 'The layout no longer carries the session guard.');
        $this->assertLessThan($guard, $block, 'The guard sits outside the block, so a theme overriding it reads app.flashes for every visitor.');

        $read = strpos($layout, '{% for label, messages in app.flashes %}');
        $this->assertNotFalse($read, 'The layout no longer reads app.flashes.');
        $this->assertLessThan($read, $guard, 'The flashes are read outside the guard.');
    }

    private function layout(): string
    {
        $path = \dirname(__DIR__, 2) . '/templates/layout.html.twig';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
