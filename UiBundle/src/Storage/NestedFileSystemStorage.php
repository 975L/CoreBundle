<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Storage;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Storage\AbstractStorage;

// Vich's own FileSystemStorage is final and can't be extended, so doRemove()/doResolvePath() are duplicated here unchanged - only doUpload() differs. Namers (see UiMediaNamer) return a name that already contains the full path relative to the mapping's upload_destination (e.g. "medias/site/block-article-42-xxx.webp"), so that "filename" in DB is self-sufficient and doesn't need to be paired with a separate directory_namer to know where a file lives. But Symfony\Component\HttpFoundation\File\File::move() silently strips everything before the last "/" in the target name, so left to Vich's default storage, uploads would flatten into one folder while removal (which doesn't go through File::move()) would still expect the nested path - looking for a file that was never actually placed there, and silently failing (Vich swallows remove() exceptions).
class NestedFileSystemStorage extends AbstractStorage
{
    protected function doUpload(PropertyMapping $mapping, File $file, ?string $dir, string $name): ?File
    {
        $name = str_replace('\\', '/', $name);
        $subDir = dirname($name);
        $basename = basename($name);
        $nestedDir = '.' === $subDir ? $dir : trim(($dir ?? '') . '/' . $subDir, '/');

        $uploadDir = $mapping->getUploadDestination() . \DIRECTORY_SEPARATOR . $nestedDir;

        if (!file_exists($uploadDir) && !@mkdir($uploadDir, recursive: true) && !is_dir($uploadDir)) {
            throw new \Exception('Could not create directory "' . $uploadDir . '"');
        }
        if (!is_dir($uploadDir)) {
            throw new \Exception('Tried to move file to directory "' . $uploadDir . '" but it is a file');
        }

        if ($file instanceof UploadedFile) {
            return $file->move($uploadDir, $basename);
        }

        $targetPathname = $uploadDir . \DIRECTORY_SEPARATOR . $basename;
        if (!copy($file->getPathname(), $targetPathname)) {
            throw new \RuntimeException('Could not copy file');
        }

        return new File($targetPathname);
    }

    protected function doRemove(PropertyMapping $mapping, ?string $dir, string $name): ?bool
    {
        $file = $this->doResolvePath($mapping, $dir, $name);

        if (!file_exists($file) || !unlink($file)) {
            throw new \Exception('Cannot remove file ' . $file);
        }

        return true;
    }

    protected function doResolvePath(PropertyMapping $mapping, ?string $dir, string $name, ?bool $relative = false): string
    {
        $path = (is_string($dir) && '' !== $dir) ? $dir . \DIRECTORY_SEPARATOR . $name : $name;

        if ($relative) {
            return $path;
        }

        return $mapping->getUploadDestination() . \DIRECTORY_SEPARATOR . $path;
    }
}
