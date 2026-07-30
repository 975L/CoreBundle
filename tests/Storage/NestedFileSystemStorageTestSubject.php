<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Storage;

use c975L\UiBundle\Storage\NestedFileSystemStorage;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\PropertyMapping;

// doUpload()/doRemove()/doResolvePath() are protected - exposed here as public passthroughs for NestedFileSystemStorageTest. Its own file (not inlined in the test class) - src/Tests classes are autoloadable by consuming apps, whose attribute route loader recursively reflects every class under the bundle root, and PSR-4 requires one file per class for that to work.
class NestedFileSystemStorageTestSubject extends NestedFileSystemStorage
{
    public function publicDoUpload(PropertyMapping $mapping, File $file, ?string $dir, string $name): ?File
    {
        return $this->doUpload($mapping, $file, $dir, $name);
    }

    public function publicDoRemove(PropertyMapping $mapping, ?string $dir, string $name): ?bool
    {
        return $this->doRemove($mapping, $dir, $name);
    }

    public function publicDoResolvePath(PropertyMapping $mapping, ?string $dir, string $name, ?bool $relative = false): string
    {
        return $this->doResolvePath($mapping, $dir, $name, $relative);
    }
}
