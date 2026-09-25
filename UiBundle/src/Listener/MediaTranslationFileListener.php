<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\TranslationRepository;
use c975L\UiBundle\Service\MediaTranslator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// The files a media shows in another language (see MediaTranslator::stageFile), which Vich knows nothing of: written once the flush saving their block goes through, taken off the disk with the media or when a language is given another one, unless another media still shows it (see TranslationRepository::isValueUsedElsewhere). Its postFlush runs after TranslationCopyListener's, so a reimport putting a file back at the path its old media leaves is seen by that guard (an order no unit test can pin)
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush, priority: -10)]
class MediaTranslationFileListener
{
    // The files of the medias being removed, noted while their translations still exist
    /** @var list<array{0: string, 1: int, 2: string}> path, media id, locale */
    private array $removed = [];

    public function __construct(
        private readonly MediaTranslator $mediaTranslator,
        private readonly TranslationRepository $repository,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $media = $args->getObject();
        if (!$media instanceof Media || null === $media->getId()) {
            return;
        }

        foreach ($this->repository->findByOwner(MediaTranslator::OWNER, $media->getId()) as $locale => $fields) {
            $path = $fields[MediaTranslator::FILE] ?? null;
            if (null !== $path) {
                $this->removed[] = [$path, $media->getId(), $locale];
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        foreach ($this->mediaTranslator->takePendingFiles() as [$file, $target, $previous, $id, $locale]) {
            // A copy rather than a move: a demo seeds these from files it reads again at every reload
            if (null !== $file && null !== $target) {
                $path = $this->projectDir . '/public/' . $target;
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0o755, true);
                }
                copy($file->getPathname(), $path);
            }

            // The name carries the file's hash, so a file replaced leaves its old one to take away, and the same file given again is left as it is
            if (null !== $previous && $previous !== $target) {
                $this->unlink($previous, $id, $locale);
            }
        }

        $removed = $this->removed;
        $this->removed = [];
        foreach ($removed as [$path, $id, $locale]) {
            $this->unlink($path, $id, $locale);
        }
    }

    private function unlink(string $path, int $id, string $locale): void
    {
        // Read back from a row an import may have written: a path leaving public/, or not named as a language's file, is nobody's media
        if (!MediaTranslator::isTranslatedFilePath($path)) {
            return;
        }

        $file = $this->projectDir . '/public/' . $path;
        if (is_file($file) && !$this->repository->isValueUsedElsewhere(MediaTranslator::OWNER, MediaTranslator::FILE, $path, $id, $locale)) {
            unlink($file);
        }
    }
}
