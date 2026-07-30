<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\BlockFixtureProviderInterface;
use c975L\UiBundle\Registry\BlockFixtureRegistry;
use PHPUnit\Framework\TestCase;

class BlockFixtureRegistryTest extends TestCase
{
    private function createProvider(array $fixtures): BlockFixtureProviderInterface
    {
        $provider = $this->createStub(BlockFixtureProviderInterface::class);
        $provider->method('getFixtures')->willReturn($fixtures);

        return $provider;
    }

    public function testHasReturnsFalseWhenNoProviderRegisteredTheKind(): void
    {
        $registry = new BlockFixtureRegistry();

        $this->assertFalse($registry->has('alert'));
        $this->assertSame([], $registry->get('alert'));
    }

    public function testHasAndGetReflectFixturesFromProviders(): void
    {
        $registry = new BlockFixtureRegistry();
        $registry->addProvider($this->createProvider(['alert' => ['' => ['type' => 'info']]]));

        $this->assertTrue($registry->has('alert'));
        $this->assertSame(['' => ['type' => 'info']], $registry->get('alert'));
    }

    // Kinds are merged across providers, so a satellite bundle's own kinds coexist with UiBundle's built-in ones
    public function testFixturesFromSeveralProvidersAreMerged(): void
    {
        $registry = new BlockFixtureRegistry();
        $registry->addProvider($this->createProvider(['alert' => ['' => ['type' => 'info']]]));
        $registry->addProvider($this->createProvider(['booking' => ['' => ['title' => 'Book now']]]));

        $this->assertTrue($registry->has('alert'));
        $this->assertTrue($registry->has('booking'));
    }

    // A later provider registering the same kind overrides the earlier one, same as array_merge()
    public function testLaterProviderOverridesEarlierOneForTheSameKind(): void
    {
        $registry = new BlockFixtureRegistry();
        $registry->addProvider($this->createProvider(['alert' => ['' => ['type' => 'info']]]));
        $registry->addProvider($this->createProvider(['alert' => ['' => ['type' => 'danger']]]));

        $this->assertSame(['' => ['type' => 'danger']], $registry->get('alert'));
    }

    // Several variants (e.g. alert's info/success/warning/danger styles) are just several keys under the same kind
    public function testGetReturnsEveryVariantForAKind(): void
    {
        $registry = new BlockFixtureRegistry();
        $registry->addProvider($this->createProvider([
            'alert' => [
                'Info' => ['type' => 'info'],
                'Danger' => ['type' => 'danger'],
            ],
        ]));

        $this->assertSame(
            ['Info' => ['type' => 'info'], 'Danger' => ['type' => 'danger']],
            $registry->get('alert')
        );
    }
}
