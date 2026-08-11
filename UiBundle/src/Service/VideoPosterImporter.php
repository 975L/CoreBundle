<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Video\VideoPlatform;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Copies a platform's own still into a local Media, once, when the editor asked for it by ticking "importPoster" - never on its own and never at render time
// Importing rather than hotlinking is the whole point: the file ends up served from the site's own domain, so no third-party origin to open in the CSP, no visitor IP handed to the platform, and nothing to consent to before the poster is even visible
// The tick is a one-shot action, not a stored preference: it is cleared here as soon as the import is done, so the editor stays free to replace the imported image by hand, and a still that changed on the platform is re-imported by ticking the box again - a flag re-fetching on every save would silently overwrite that manual replacement instead
class VideoPosterImporter implements EventSubscriberInterface
{
    // A still is a few hundred KB; anything past this is not one, and is dropped rather than written to disk
    private const int MAX_BYTES = 4_000_000;

    private const int TIMEOUT = 8;

    // The files written by toTemporaryFile() during this request, kept to be swept once it is over
    private array $temporaryPaths = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'removeTemporaryFiles'];
    }

    // The import runs from the form's POST_SUBMIT whatever the submission turns out to be worth - as a CollectionType entry, that listener fires before the root form is validated, so a rejected submission has downloaded its still all the same and Vich will never move it - swept here whatever the form's fate, a successful save having already had its file moved away
    public function removeTemporaryFiles(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryPaths = [];
    }

    // Imports the poster of a "video_iframe" block whose editor ticked the box, as the block's own (single) media - a no-op for every other kind, for an unticked box, and for a url belonging to no platform that serves a guessable still
    public function importIfRequested(Block $block): void
    {
        $data = $block->getData();
        if ('video_iframe' !== $block->getKind() || empty($data['importPoster'])) {
            return;
        }

        // Cleared whatever happens next: a failed import must not leave the box ticked, or every later save would retry a download that already did not answer
        unset($data['importPoster']);
        $block->setData($data);

        $file = $this->fetch($data['src'] ?? null);
        if (null === $file) {
            return;
        }

        $this->attach($block, $file);
    }

    // Downloads the first candidate that answers, as a temporary file Vich then names, resizes and converts to webp like any upload (see UiMediaNamer / VichImageResizeListener) - null when the url belongs to no platform, the platform serves no guessable still, or none of its candidates answered
    public function fetch(?string $url): ?File
    {
        $video = VideoPlatform::resolve($url);
        if (null === $video) {
            return null;
        }

        foreach ($video->posterUrls() as $posterUrl) {
            $file = $this->download($posterUrl);
            if (null !== $file) {
                return $file;
            }
        }

        return null;
    }

    // A missing "maxresdefault" answers 404, which is an expected outcome here rather than a failure - hence the next candidate instead of an exception
    private function download(string $url): ?File
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => self::TIMEOUT,
                'max_duration' => self::TIMEOUT,
            ]);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $content = $response->getContent();
        } catch (\Throwable $exception) {
            $this->logger?->warning('Video poster import failed: ' . $exception->getMessage(), ['url' => $url]);

            return null;
        }

        if ('' === $content || strlen($content) > self::MAX_BYTES) {
            return null;
        }

        return $this->toTemporaryFile($content);
    }

    // Written to disk before anything is decided about it: the mime type has to be read off the bytes rather than off the response header, which is the same rule the upload path follows (see MediaUploadType::mimeTypeConstraints)
    private function toTemporaryFile(string $content): ?File
    {
        $path = tempnam(sys_get_temp_dir(), 'ui_poster_');
        if (false === $path) {
            return null;
        }

        // Registered as soon as the file exists rather than once it is written: a failed write - a full disk, a quota - would otherwise leave a 0-byte file the sweep knows nothing about
        $this->temporaryPaths[] = $path;

        if (false === file_put_contents($path, $content)) {
            return null;
        }

        $file = new File($path);
        if (!str_starts_with((string) $file->getMimeType(), 'image/')) {
            unlink($path);

            return null;
        }

        return $file;
    }

    // Replaces the block's existing poster rather than adding a second one: a player has one, and leaving the old row behind would make "which of these two is the poster" a question the template has to answer
    private function attach(Block $block, File $file): void
    {
        $media = $block->getMedias()->first() ?: null;

        if (!$media instanceof Media) {
            $media = new Media();
            $block->addMedia($media);
        }

        $media->setFile($file);
    }
}
