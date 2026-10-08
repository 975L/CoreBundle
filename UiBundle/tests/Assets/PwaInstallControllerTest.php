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

// The pwa controller keeps the browser's install offer and drives the install button a template writes as its target
class PwaInstallControllerTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/pwa.js';

    // Kept at the module's level, so a Turbo visit reconnecting the controller still finds it
    public function testTheOfferIsKeptOutsideTheControllerInstance(): void
    {
        $script = $this->read(self::CONTROLLER_JS);
        $classStart = (int) strpos($script, 'export default class');

        $this->assertGreaterThan(0, $classStart);
        $this->assertLessThan($classStart, (int) strpos($script, 'let installPrompt = null;'));
        $this->assertLessThan($classStart, (int) strpos($script, 'window.addEventListener("beforeinstallprompt"'));
        $this->assertStringContainsString('event.preventDefault();', $script);
    }

    // Hidden while no offer stands, and again once the app is installed
    public function testTheButtonFollowsTheOffer(): void
    {
        $script = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('static targets = ["install"];', $script);
        $this->assertStringContainsString('installTargetConnected(button)', $script);
        $this->assertStringContainsString('installTargetDisconnected(button)', $script);
        $this->assertStringContainsString('button.hidden = !canInstall();', $script);
        $this->assertStringContainsString('return null !== installPrompt || explainsIosInstall;', $script);
        $this->assertStringContainsString('window.addEventListener("appinstalled"', $script);
    }

    // The offer is used once: cleared before the browser's dialog opens
    public function testTheInstallActionUsesTheOfferOnce(): void
    {
        $script = $this->read(self::CONTROLLER_JS);
        $install = substr($script, (int) strpos($script, 'async install()'));

        $this->assertStringContainsString('if (null === installPrompt) {', $install);
        $this->assertLessThan(strpos($install, 'await prompt.prompt();'), strpos($install, 'installPrompt = null;'));
    }

    // iOS has no install offer: outside the installed app the button opens the layout's explanation instead
    public function testTheButtonExplainsTheInstallOnIos(): void
    {
        $install = substr($this->read(self::CONTROLLER_JS), (int) strpos($this->read(self::CONTROLLER_JS), 'async install()'));

        $this->assertStringContainsString('document.getElementById("pwa-ios-install")?.showModal();', $install);
        $this->assertStringContainsString('<twig:c975LUi:Dialog:Dialog id="pwa-ios-install"', $this->read('templates/layout.html.twig'));
    }

    // Lazily registered under the identifier the layout mounts on the body
    public function testTheControllerIsRegisteredUnderThePwaIdentifier(): void
    {
        $this->assertStringContainsString("pwa: () => import('./js/pwa.js')", $this->read('assets/controllers.js'));
    }

    private function read(string $file): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, half of the mechanism this test checks is gone.', $file));

        return (string) file_get_contents($path);
    }
}
