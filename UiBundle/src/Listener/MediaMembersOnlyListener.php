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
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

// Moves a stored PDF between public/ and Media::MEMBERS_ONLY_DIRECTORY when its "members only" box is ticked or unticked without a new upload - an upload lands where the flag says on its own (see VichImageResizeListener), a file already on disk has nobody else to take it across. Deferred to postFlush like MediaFileRemoveListener, so a flush that throws leaves the file where its row still says it is
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class MediaMembersOnlyListener
{
    private const string PUBLIC_DIRECTORY = 'public';

    /** @var list<array{from: string, to: string}> */
    private array $pendingMoves = [];

    /** @var list<string> */
    private array $pendingRemovals = [];

    private readonly Filesystem $filesystem;

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        $this->filesystem = new Filesystem();
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $media = $args->getObject();
        if (!$media instanceof Media || !$media->isPdf() || null !== $media->getFile() || !$args->hasChangedField('membersOnly')) {
            return;
        }

        $filename = (string) $media->getFilename();
        $membersOnly = $media->isMembersOnly();

        $this->pendingMoves[] = [
            'from' => $this->path($membersOnly ? self::PUBLIC_DIRECTORY : Media::MEMBERS_ONLY_DIRECTORY, $filename),
            'to' => $this->path($membersOnly ? Media::MEMBERS_ONLY_DIRECTORY : self::PUBLIC_DIRECTORY, $filename),
        ];

        // The first page stays readable by anyone as long as its thumbnail does, and none is made for a document reserved to members (see VichPdfThumbnailListener)
        if ($membersOnly) {
            $this->pendingRemovals[] = $this->path(self::PUBLIC_DIRECTORY, VichPdfThumbnailListener::toWebpPath($filename));
        }
    }

    public function postFlush(): void
    {
        foreach ($this->pendingMoves as ['from' => $from, 'to' => $to]) {
            if (is_file($from)) {
                $this->filesystem->mkdir(\dirname($to));
                $this->filesystem->rename($from, $to, true);
            }
        }

        $this->filesystem->remove($this->pendingRemovals);

        $this->pendingMoves = [];
        $this->pendingRemovals = [];
    }

    private function path(string $directory, string $filename): string
    {
        return $this->projectDir . '/' . $directory . '/' . $filename;
    }
}
