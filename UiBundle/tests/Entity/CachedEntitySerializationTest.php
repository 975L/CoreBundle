<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Tests\Fixtures\DummyUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Blocks and medias are cached whole (see SiteBundle's MenuExtension, MediaExtension::preloadSingletonRoles()): serialize() used to initialize their lazy user, putting the User row in the cache or throwing on every page once that account was deleted
class CachedEntitySerializationTest extends TestCase
{
    // One entity of each kind carrying a user, with a getter proving the rest of it survives the round-trip
    /** @return iterable<string, array{Block|Media, string, string}> */
    public static function entities(): iterable
    {
        yield 'block' => [new Block()->setKind('menu_link'), 'getKind', 'menu_link'];
        yield 'media' => [new Media()->setFilename('logo.png'), 'getFilename', 'logo.png'];
    }

    // The lazy user stays unloaded and comes back null, every other property intact
    #[DataProvider('entities')]
    public function testSerializingLeavesTheLazyUserOut(Block | Media $entity, string $getter, string $expected): void
    {
        $user = new \ReflectionClass(DummyUser::class)->newLazyGhost(static function (): void {
            throw new \LogicException('The user must not be loaded');
        });
        $entity->setUser($user);

        $copy = unserialize(serialize($entity));

        $this->assertTrue(new \ReflectionClass(DummyUser::class)->isUninitializedLazyObject($user));
        $this->assertNull($copy->getUser());
        $this->assertSame($expected, $copy->{$getter}());
    }
}
