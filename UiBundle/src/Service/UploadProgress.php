<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

// The two halves of the progress bar shown while a form posts files (see assets/js/upload-progress.js): what the form has to declare for the controller to arm itself, and what the controller has to be answered with once the batch is in. A service rather than a line of attributes copied into each form type - the three messages the bar states have no translator on the javascript side, and every bundle uploading anything needs the same ones
class UploadProgress
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Merged into what the form already declares rather than replacing it: a form carrying a controller or an action of its own keeps it, Stimulus reading both attributes as space-separated lists
    /**
     * @param array<string, mixed> $attr
     *
     * @return array<string, mixed>
     */
    public function formAttr(array $attr = []): array
    {
        return array_merge($attr, [
            'data-controller' => trim((string) ($attr['data-controller'] ?? '') . ' upload-progress'),
            'data-action' => trim((string) ($attr['data-action'] ?? '') . ' submit->upload-progress#send'),
            'data-upload-progress-uploading-message-value' => $this->translator->trans('label.upload_progress_uploading', [], 'ui'),
            'data-upload-progress-processing-message-value' => $this->translator->trans('label.upload_progress_processing', [], 'ui'),
            'data-upload-progress-failed-message-value' => $this->translator->trans('label.upload_progress_failed', [], 'ui'),
        ]);
    }

    // Where a controller would redirect after a submission: XMLHttpRequest follows a 302 itself, so the arrival page would be built - and its flash message read - inside a request nobody sees, leaving the screen that finally loads without the "medias added" it was meant to show. The url is handed over instead, for the bar to send the browser there
    public function redirect(Request $request, string $url): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['redirect' => $url]);
        }

        return new RedirectResponse($url);
    }
}
