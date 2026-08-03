<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\FormActionInterface;
use c975L\UiBundle\Registry\FormActionRegistry;
use PHPUnit\Framework\TestCase;

class FormActionRegistryTest extends TestCase
{
    private function createProvider(string $key): FormActionInterface
    {
        $provider = $this->createStub(FormActionInterface::class);
        $provider->method('getKey')->willReturn($key);

        return $provider;
    }

    public function testHasReturnsFalseWhenNoProviderRegisteredTheKey(): void
    {
        $registry = new FormActionRegistry();

        $this->assertFalse($registry->has('send_email'));
    }

    public function testGetThrowsWhenNoProviderRegisteredTheKey(): void
    {
        $registry = new FormActionRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $registry->get('send_email');
    }

    public function testHasAndGetReflectActionsFromProviders(): void
    {
        $registry = new FormActionRegistry();
        $action = $this->createProvider('send_email');
        $registry->addProvider($action);

        $this->assertTrue($registry->has('send_email'));
        $this->assertSame($action, $registry->get('send_email'));
    }

    // A later provider registering the same key overrides the earlier one
    public function testLaterProviderOverridesEarlierOneForTheSameKey(): void
    {
        $registry = new FormActionRegistry();
        $registry->addProvider($this->createProvider('send_email'));
        $second = $this->createProvider('send_email');
        $registry->addProvider($second);

        $this->assertSame($second, $registry->get('send_email'));
    }
}
