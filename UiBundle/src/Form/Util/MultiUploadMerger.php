<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Util;

use Symfony\Component\HttpFoundation\File\UploadedFile;

// Splits a multi-file input's submitted files into individual "medias" collection entries, appended after the existing ones - each entry has the same shape a single manually-added collection row would submit ("file" => ["file" => UploadedFile]), so MediaUploadType/VichFileType process it exactly like any other new media (see BlockType, which calls this from its PRE_SUBMIT listener).
final class MultiUploadMerger
{
    public function __construct()
    {
    }

    public static function merge(array $medias, array $files): array
    {
        $position = count($medias);
        $nextKey = 0;
        foreach (array_keys($medias) as $key) {
            if (is_numeric($key)) {
                $nextKey = max($nextKey, (int) $key + 1);
            }
        }

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $medias[$nextKey] = [
                'file' => ['file' => $file],
                'position' => (string) $position,
            ];
            ++$nextKey;
            ++$position;
        }

        return $medias;
    }
}
