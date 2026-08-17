<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\UploadProgress;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class UploadProgressTest extends TestCase
{
    private function uploadProgress(): UploadProgress
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new UploadProgress($translator);
    }

    public function testFormAttrCarriesTheControllerItsActionAndItsThreeMessages(): void
    {
        $attr = $this->uploadProgress()->formAttr();

        $this->assertSame([
            'data-controller' => 'upload-progress',
            'data-action' => 'submit->upload-progress#send',
            'data-upload-progress-uploading-message-value' => 'label.upload_progress_uploading',
            'data-upload-progress-processing-message-value' => 'label.upload_progress_processing',
            'data-upload-progress-failed-message-value' => 'label.upload_progress_failed',
        ], $attr);
    }

    // Stimulus reads both attributes as space-separated lists: a form already carrying a controller of its own keeps it rather than losing it to the bar
    public function testFormAttrComposesWithWhatTheFormAlreadyDeclares(): void
    {
        $attr = $this->uploadProgress()->formAttr([
            'data-controller' => 'gallery-upload-limits',
            'data-action' => 'change->gallery-upload-limits#check',
            'data-gallery-upload-limits-max-files-value' => 20,
        ]);

        $this->assertSame('gallery-upload-limits upload-progress', $attr['data-controller']);
        $this->assertSame('change->gallery-upload-limits#check submit->upload-progress#send', $attr['data-action']);
        $this->assertSame(20, $attr['data-gallery-upload-limits-max-files-value']);
    }

    // XMLHttpRequest follows a 302 by itself, so the arrival page would be built - and its flash read - inside a request nobody sees
    public function testRedirectHandsTheUrlBackToTheBarInsteadOfRedirecting(): void
    {
        $request = Request::create('/management/gallery-upload', 'POST');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->uploadProgress()->redirect($request, '/management/gallery/42/edit');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"redirect":"\/management\/gallery\/42\/edit"}', $response->getContent());
    }

    // A form posted without javascript, or by a browser the controller never armed, goes on being redirected
    public function testRedirectStaysARedirectForAPlainSubmission(): void
    {
        $response = $this->uploadProgress()->redirect(Request::create('/management/gallery-upload', 'POST'), '/management/gallery/42/edit');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/management/gallery/42/edit', $response->getTargetUrl());
    }
}
