<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Service\SvgTextDetector;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Event\Event;

// Warns, on the upload itself, about an SVG still drawing its text with a font - the defect an author is the last person able to see, their own machine being the one that has the font. Hooked on the upload rather than on a CRUD controller so every path is covered at once: a site graphic, a block's media, a gallery photo. A flash and not a validation error: the file is perfectly storable, it just will not look the same to everyone
#[AsEventListener(event: 'vich_uploader.post_upload', method: 'onPostUpload', priority: 10)]
class SvgTextWarningListener
{
    public function __construct(
        private readonly SvgTextDetector $svgTextDetector,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function onPostUpload(Event $event): void
    {
        // A content_import, a fixture or a console command uploads with nobody to warn
        $request = $this->requestStack->getMainRequest();
        if (null === $request || !$request->hasSession()) {
            return;
        }

        // Same resolution as VichImageResizeListener's, which runs right after this one: a lower priority, so an icon role's SVG is still SVG when read here and a PNG by the time it is stored
        $filename = $event->getMapping()->getFileName($event->getObject());
        $absolutePath = $this->projectDir . '/public/' . $filename;

        if (!$this->svgTextDetector->drawsText($absolutePath)) {
            return;
        }

        // Only punctuation is built here, the sentence itself staying in the catalogue
        $families = $this->svgTextDetector->fontFamilies($absolutePath);
        $request->getSession()->getFlashBag()->add('warning', $this->translator->trans(
            'text.svg_text_not_vectorized',
            [
                '%filename%' => $filename,
                '%fonts%' => [] === $families ? '' : ' (' . implode(', ', $families) . ')',
            ],
            'ui',
        ));
    }
}
