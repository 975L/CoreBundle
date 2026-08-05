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

// The identifier is a cross-bundle contract: SiteBundle's PageCrudController and GalleryBundle's GalleryCategoryCrudController write it as a plain data-controller string, and neither end drifts loudly - the repository has no browser to run the controller in
class TitleConfirmControllerRegistrationTest extends TestCase
{
    private const CONTROLLER_JS = 'assets/js/title-confirm.js';
    private const ADMIN_BARREL = 'assets/controllers-admin.js';
    private const IDENTIFIER = 'title-confirm';

    public function testTheControllerIsRegisteredInTheAdminBarrel(): void
    {
        $barrel = $this->read(self::ADMIN_BARREL);

        $this->assertStringContainsString("import TitleConfirmController from './js/title-confirm.js';", $barrel);
        $this->assertStringContainsString(sprintf("app.register('%s', TitleConfirmController);", self::IDENTIFIER), $barrel);
    }

    // Kebab-case on purpose: Stimulus derives data-title-confirm-message-value from the identifier as registered, and a camelCase one would look for data-titleConfirm-* instead
    public function testTheMessageIsReadFromAValue(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('static values = { message: String };', $controller);
        $this->assertStringContainsString('this.messageValue', $controller);
    }

    // It reuses EasyAdmin's own action-confirmation modal rather than a native confirm(), and answers nothing at all on a page that doesn't render it (the "new" crud page)
    public function testItReusesEasyAdminsConfirmationModal(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString("querySelector('#modal-action-confirmation')", $controller);
        $this->assertStringContainsString('window.bootstrap', $controller);
    }

    // Named "confirm" because that is the method both consumers bind their focus/click actions to
    public function testItExposesTheConfirmAction(): void
    {
        $this->assertStringContainsString('confirm()', $this->read(self::CONTROLLER_JS));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
