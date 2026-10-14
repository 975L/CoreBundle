<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// A page counts what it played to its end under the token its browser already holds, never one it makes up
class PlayCountControllerTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/play-count.js';
    private const string BARREL = 'assets/controllers.js';

    // Lazy, and in kebab-case so "data-ui-play-count-url-value" binds
    public function testTheControllerIsRegisteredLazilyUnderItsKebabCaseIdentifier(): void
    {
        $barrel = $this->read(self::BARREL);

        $this->assertStringContainsString("'ui-play-count': () => import('./js/play-count.js'),", $barrel);
        $this->assertStringNotContainsString('uiPlayCount:', $barrel);
    }

    // Reading the store only: a listener is no one who asked to be remembered
    public function testNoTokenIsEverWrittenToTheBrowser(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('import { read } from "./favorite-store.js";', $controller);
        $this->assertStringNotContainsString('newToken', $controller);
        $this->assertStringNotContainsString('write(', $controller);
    }

    // Posted as json, which is what the route's same-origin check expects
    public function testThePlayIsPostedAsJsonToTheUrlValue(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('fetch(this.urlValue, {', $controller);
        $this->assertStringContainsString('"Content-Type": "application/json"', $controller);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/' . $path);
    }
}
