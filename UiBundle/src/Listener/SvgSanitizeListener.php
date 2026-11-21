<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use enshrined\svgSanitize\Sanitizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Event\Event;

// Strips every uploaded SVG of its scripts, event handlers and remote references before anything serves it: the file lands under public/ and a browser opening it directly runs what it carries on the site's own origin. Hooked on the upload so every path is covered at once (a form, an import, another bundle's entity), and ahead of the other post_upload listeners so they all read the cleaned file. An SVG that cannot be read as such is removed and refused
#[AsEventListener(event: 'vich_uploader.post_upload', method: 'onPostUpload', priority: 20)]
class SvgSanitizeListener
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // Rewrites the stored file with its sanitized markup, an SVG by its name or by its bytes
    public function onPostUpload(Event $event): void
    {
        $filename = (string) $event->getMapping()->getFileName($event->getObject());
        $absolutePath = $this->projectDir . '/public/' . $filename;
        if ('' === $filename || !is_file($absolutePath) || !self::isSvg($absolutePath)) {
            return;
        }

        // A file that does not parse is no image anyone can be shown, and no file to leave where a browser would open it
        $sanitized = self::sanitize((string) file_get_contents($absolutePath));
        if (null === $sanitized) {
            unlink($absolutePath);

            throw new \RuntimeException(\sprintf('The SVG "%s" could not be sanitized and was refused.', $filename));
        }

        file_put_contents($absolutePath, $sanitized);
    }

    // The markup without scripts, event handlers nor remote references, null when it cannot be parsed
    public static function sanitize(string $svg): ?string
    {
        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);
        $sanitized = $sanitizer->sanitize($svg);

        return \is_string($sanitized) && '' !== trim($sanitized) ? $sanitized : null;
    }

    // Its name says svg (what the web server serves it as) or its bytes do (an icon role's file, stored under the role's extension)
    public static function isSvg(string $absolutePath): bool
    {
        return 'svg' === strtolower(pathinfo($absolutePath, \PATHINFO_EXTENSION)) || str_contains((string) new File($absolutePath)->getMimeType(), 'svg');
    }
}
