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

// The bar is written across three files no browser here runs - the controller, the service arming it and the stylesheet painting it - so each end is checked against what the other two assume
class UploadProgressControllerTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/upload-progress.js';

    private const string ADMIN_BARREL = 'assets/controllers-admin.js';

    private const string SERVICE = 'src/Service/UploadProgress.php';

    private const string STYLESHEET = 'sass/management/_upload-progress.scss';

    private const string IDENTIFIER = 'upload-progress';

    public function testTheControllerIsRegisteredInTheAdminBarrel(): void
    {
        $barrel = $this->read(self::ADMIN_BARREL);

        $this->assertStringContainsString("import UploadProgressController from './js/upload-progress.js';", $barrel);
        $this->assertStringContainsString(sprintf("app.register('%s', UploadProgressController);", self::IDENTIFIER), $barrel);
    }

    // It takes over the submit of the form it sits on, so it is armed per form and never mounted on <body> like the barrel's other admin controllers
    public function testItIsNotMountedOnTheBodyAlongsideTheOthers(): void
    {
        $barrel = $this->read(self::ADMIN_BARREL);
        $mounted = substr($barrel, (int) strpos($barrel, 'document.body.setAttribute'));

        $this->assertStringNotContainsString(self::IDENTIFIER, $mounted);
    }

    // Kebab-case on purpose: Stimulus derives the data-upload-progress-*-message-value attributes the service writes from the identifier as registered
    public function testTheServiceWritesTheThreeValuesTheControllerDeclares(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);
        $service = $this->read(self::SERVICE);

        foreach (['uploading', 'processing', 'failed'] as $phase) {
            $this->assertStringContainsString(sprintf('%sMessage: String,', $phase), $controller);
            $this->assertStringContainsString(sprintf('data-%s-%s-message-value', self::IDENTIFIER, $phase), $service);
        }
    }

    public function testTheServiceBindsTheSubmitToTheMethodTheControllerHolds(): void
    {
        $this->assertStringContainsString(sprintf('submit->%s#send', self::IDENTIFIER), $this->read(self::SERVICE));
        $this->assertStringContainsString('send(event) {', $this->read(self::CONTROLLER_JS));
    }

    // The header is the whole handshake: the service answers a url rather than a redirect only to a submission carrying it, and Symfony's isXmlHttpRequest() reads that one header alone
    public function testBothEndsAgreeOnTheHeaderThatAsksForAUrl(): void
    {
        $this->assertStringContainsString('request.setRequestHeader("X-Requested-With", "XMLHttpRequest");', $this->read(self::CONTROLLER_JS));
        $this->assertStringContainsString('$request->isXmlHttpRequest()', $this->read(self::SERVICE));
    }

    public function testBothEndsAgreeOnTheJsonKeyCarryingTheUrl(): void
    {
        $this->assertStringContainsString('JSON.parse(request.responseText).redirect', $this->read(self::CONTROLLER_JS));
        $this->assertStringContainsString("['redirect' => \$url]", $this->read(self::SERVICE));
    }

    // XMLHttpRequest and not fetch: it is the only api a browser reports the upload of, and the transfer is what this bar exists to show
    public function testTheTransferIsMeasuredOnTheUploadItself(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('new XMLHttpRequest()', $controller);
        $this->assertStringContainsString('request.upload.addEventListener("progress"', $controller);
        $this->assertStringContainsString('progress.lengthComputable', $controller);
    }

    // The two phases told apart: the transfer has a percentage, the processing that follows has none and gets the browser's own indeterminate bar
    public function testTheProcessingPhaseDropsTheValueRatherThanFreezingAtFullBar(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('request.upload.addEventListener("load", () => this.processing());', $controller);
        $this->assertStringContainsString('this.bar.removeAttribute("value");', $controller);
    }

    // A batch the network refused is one to send again, where a rejected form comes back as its own html and is swapped in place
    public function testTheSubmitIsHandedBackOnFailureAndOnlyThen(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('this.toggleSubmit(true);', $controller);
        $this->assertStringContainsString('this.toggleSubmit(false);', $controller);
        $this->assertStringContainsString('this.form.replaceWith(form);', $controller);
    }

    // The panel is built by the controller, so the classes it writes are the ones the stylesheet paints
    public function testTheClassesTheControllerWritesAreThoseTheStylesheetPaints(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);
        $stylesheet = $this->read(self::STYLESHEET);

        foreach (['upload-progress', 'upload-progress-bar', 'upload-progress-status'] as $class) {
            $this->assertStringContainsString(sprintf('"%s"', $class), $controller);
            $this->assertStringContainsString(sprintf('.%s {', $class), $stylesheet);
        }
    }

    public function testTheStylesheetIsImportedByTheManagementSheet(): void
    {
        $this->assertStringContainsString("@use 'management/_upload-progress.scss';", $this->read('sass/management.scss'));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
