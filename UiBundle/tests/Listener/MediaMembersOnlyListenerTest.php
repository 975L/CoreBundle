<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Listener\MediaMembersOnlyListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

class MediaMembersOnlyListenerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/media-members-only-listener-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    private function put(string $path): void
    {
        new Filesystem()->dumpFile($this->projectDir . '/' . $path, 'content');
    }

    // The box as it is now, the changeset holding the value it had before
    private function createUpdateEventArgs(Media $media): PreUpdateEventArgs
    {
        $changeSet = ['membersOnly' => [!$media->isMembersOnly(), $media->isMembersOnly()]];

        return new PreUpdateEventArgs($media, $this->createStub(EntityManagerInterface::class), $changeSet);
    }

    private function createMedia(string $filename, bool $membersOnly): Media
    {
        return new Media()->setFilename($filename)->setMembersOnly($membersOnly);
    }

    public function testTickingTheBoxTakesTheDocumentOutOfPublicAndDropsItsThumbnail(): void
    {
        $this->put('public/medias/site/tree.pdf');
        $this->put('public/medias/site/tree.webp');

        $listener = new MediaMembersOnlyListener($this->projectDir);
        $listener->preUpdate($this->createUpdateEventArgs($this->createMedia('medias/site/tree.pdf', true)));
        $listener->postFlush();

        $this->assertFileDoesNotExist($this->projectDir . '/public/medias/site/tree.pdf');
        $this->assertFileDoesNotExist($this->projectDir . '/public/medias/site/tree.webp');
        $this->assertFileExists($this->projectDir . '/private/medias/site/tree.pdf');
    }

    public function testUntickingTheBoxBringsTheDocumentBackToPublic(): void
    {
        $this->put('private/medias/site/tree.pdf');

        $listener = new MediaMembersOnlyListener($this->projectDir);
        $listener->preUpdate($this->createUpdateEventArgs($this->createMedia('medias/site/tree.pdf', false)));
        $listener->postFlush();

        $this->assertFileDoesNotExist($this->projectDir . '/private/medias/site/tree.pdf');
        $this->assertFileExists($this->projectDir . '/public/medias/site/tree.pdf');
    }

    // A flush that throws leaves the file where its row still says it is
    public function testNothingMovesBeforeTheFlushHasGoneThrough(): void
    {
        $this->put('public/medias/site/tree.pdf');

        new MediaMembersOnlyListener($this->projectDir)->preUpdate($this->createUpdateEventArgs($this->createMedia('medias/site/tree.pdf', true)));

        $this->assertFileExists($this->projectDir . '/public/medias/site/tree.pdf');
    }

    // The new file lands where the flag says through the upload listeners, and the one it replaces is MediaFileRemoveListener's
    public function testAnUploadInTheSameSubmitIsLeftToTheUploadListeners(): void
    {
        $this->put('public/medias/site/tree.pdf');
        $this->put('new-upload.pdf');

        $media = $this->createMedia('medias/site/tree.pdf', true);
        $media->setFile(new File($this->projectDir . '/new-upload.pdf'));

        $listener = new MediaMembersOnlyListener($this->projectDir);
        $listener->preUpdate($this->createUpdateEventArgs($media));
        $listener->postFlush();

        $this->assertFileExists($this->projectDir . '/public/medias/site/tree.pdf');
        $this->assertFileDoesNotExist($this->projectDir . '/private/medias/site/tree.pdf');
    }

    // An image carries -thumb/-highres siblings a move would leave behind
    public function testAnImageIsNeverMoved(): void
    {
        $this->put('public/medias/site/photo.webp');

        $media = $this->createMedia('medias/site/photo.webp', true);
        $changeSet = ['membersOnly' => [false, true]];

        $listener = new MediaMembersOnlyListener($this->projectDir);
        $listener->preUpdate(new PreUpdateEventArgs($media, $this->createStub(EntityManagerInterface::class), $changeSet));
        $listener->postFlush();

        $this->assertFileExists($this->projectDir . '/public/medias/site/photo.webp');
    }
}
