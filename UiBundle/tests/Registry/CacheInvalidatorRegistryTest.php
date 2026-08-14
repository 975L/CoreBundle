<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\CacheInvalidatorInterface;
use c975L\UiBundle\Registry\CacheInvalidatorRegistry;
use PHPUnit\Framework\TestCase;

class CacheInvalidatorRegistryTest extends TestCase
{
    private function createInvalidator(callable $behaviour): CacheInvalidatorInterface
    {
        return new class ($behaviour) implements CacheInvalidatorInterface {
            public function __construct(private $behaviour)
            {
            }

            public function invalidate(): void
            {
                ($this->behaviour)();
            }
        };
    }

    public function testEveryRegisteredInvalidatorIsCalled(): void
    {
        $called = [];
        $registry = new CacheInvalidatorRegistry();
        $registry->addProvider($this->createInvalidator(static function () use (&$called): void {
            $called[] = 'twig';
        }));
        $registry->addProvider($this->createInvalidator(static function () use (&$called): void {
            $called[] = 'doctrine';
        }));

        $registry->invalidateAll();

        $this->assertSame(['twig', 'doctrine'], $called);
    }

    // An app with nothing of its own to empty is the normal case, and the dashboard tile must not need one
    public function testInvalidatingWithNoRegisteredInvalidatorIsHarmless(): void
    {
        new CacheInvalidatorRegistry()->invalidateAll();

        $this->expectNotToPerformAssertions();
    }

    // What the editor asked for is every cache emptied - one unreachable pool must not keep the next invalidator from running
    public function testOneFailingInvalidatorStillLetsTheOthersRun(): void
    {
        $called = [];
        $registry = new CacheInvalidatorRegistry();
        $registry->addProvider($this->createInvalidator(static fn () => throw new \RuntimeException('pool down')));
        $registry->addProvider($this->createInvalidator(static function () use (&$called): void {
            $called[] = 'doctrine';
        }));

        try {
            $registry->invalidateAll();
            $this->fail('The failure has to surface once every invalidator has had its turn.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('pool down', $exception->getMessage());
        }

        $this->assertSame(['doctrine'], $called);
    }
}
