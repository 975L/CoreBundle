<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Util;

use c975L\UiBundle\Form\Util\MultiUploadMerger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MultiUploadMergerTest extends TestCase
{
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $path) {
            @unlink($path);
        }
        $this->tmpFiles = [];
    }

    private function createUploadedFile(string $originalName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ui-multi-upload-test-');
        $this->tmpFiles[] = $path;

        return new UploadedFile($path, $originalName, null, null, true);
    }

    // Each uploaded file becomes its own "medias" entry, in the same shape a single manually-added collection row would submit (see MediaUploadType/VichFileType)
    public function testMergeAddsOneEntryPerFile(): void
    {
        $fileA = $this->createUploadedFile('a.jpg');
        $fileB = $this->createUploadedFile('b.jpg');

        $medias = MultiUploadMerger::merge([], [$fileA, $fileB]);

        $this->assertCount(2, $medias);
        $this->assertSame($fileA, $medias[0]['file']['file']);
        $this->assertSame($fileB, $medias[1]['file']['file']);
    }

    // Positions continue after the existing entries instead of restarting at 0, so uploaded files land at the end of the collection
    public function testMergeAssignsSequentialPositionsAfterExistingEntries(): void
    {
        $existing = [0 => ['id' => '1', 'position' => '0']];
        $file = $this->createUploadedFile('c.jpg');

        $medias = MultiUploadMerger::merge($existing, [$file]);

        $this->assertSame('1', $medias[1]['position']);
    }

    // New entries must use keys the existing collection doesn't already occupy, whatever those existing keys are (e.g. after a deletion leaves a gap), or they'd silently overwrite an entry
    public function testMergeUsesKeysNotAlreadyPresentInExistingEntries(): void
    {
        $existing = [5 => ['id' => '1']];
        $file = $this->createUploadedFile('d.jpg');

        $medias = MultiUploadMerger::merge($existing, [$file]);

        $this->assertArrayHasKey(5, $medias);
        $this->assertArrayHasKey(6, $medias);
        $this->assertSame($file, $medias[6]['file']['file']);
    }

    // A failed/empty file input slot (null, per PHP's multi-file upload behavior) must be skipped rather than turned into a bogus entry
    public function testMergeSkipsNonUploadedFileEntries(): void
    {
        $file = $this->createUploadedFile('e.jpg');

        $medias = MultiUploadMerger::merge([], [null, $file]);

        $this->assertCount(1, $medias);
        $this->assertSame($file, $medias[0]['file']['file']);
    }

    public function testMergeWithNoFilesReturnsExistingEntriesUnchanged(): void
    {
        $existing = [0 => ['id' => '1']];

        $medias = MultiUploadMerger::merge($existing, []);

        $this->assertSame($existing, $medias);
    }
}
