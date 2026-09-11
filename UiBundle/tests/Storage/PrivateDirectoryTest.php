<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Storage;

use c975L\UiBundle\Contract\VichPrivateFileInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Storage\PrivateDirectory;
use PHPUnit\Framework\TestCase;

class PrivateDirectoryTest extends TestCase
{
    // A whole entity class kept private, as ShopBundle's paid downloads are
    public function testAPrivateEntityClassKeepsItsOwnDirectory(): void
    {
        $entity = new class implements VichPrivateFileInterface {
            public function getPrivateDirectory(): string
            {
                return 'private/downloads';
            }
        };

        $this->assertSame('private/downloads', PrivateDirectory::resolve($entity));
    }

    public function testAPdfReservedToMembersGoesToTheMembersOnlyDirectory(): void
    {
        $media = new Media()->setFilename('medias/site/tree.pdf')->setMembersOnly(true);

        $this->assertSame(Media::MEMBERS_ONLY_DIRECTORY, PrivateDirectory::resolve($media));
    }

    // An image carries -thumb/-highres siblings a move would leave behind in public/
    public function testAnImageStaysPublicWhateverItsFlag(): void
    {
        $media = new Media()->setFilename('medias/site/photo.webp')->setMembersOnly(true);

        $this->assertNull(PrivateDirectory::resolve($media));
    }

    public function testAMediaNotReservedToMembersStaysPublic(): void
    {
        $this->assertNull(PrivateDirectory::resolve(new Media()->setFilename('medias/site/tree.pdf')));
    }

    public function testAnyOtherObjectStaysPublic(): void
    {
        $this->assertNull(PrivateDirectory::resolve(new \stdClass()));
    }
}
