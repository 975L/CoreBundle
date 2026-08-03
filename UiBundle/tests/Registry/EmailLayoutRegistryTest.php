<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\EmailLayoutProviderInterface;
use c975L\UiBundle\Registry\EmailLayoutRegistry;
use PHPUnit\Framework\TestCase;

class EmailLayoutRegistryTest extends TestCase
{
    public function testWrapReturnsNullWhenNoProviders(): void
    {
        $registry = new EmailLayoutRegistry();

        $this->assertNull($registry->wrap('<p>hello</p>'));
    }

    public function testWrapDelegatesToRegisteredProvider(): void
    {
        $provider = $this->createStub(EmailLayoutProviderInterface::class);
        $provider->method('wrap')->willReturn('<html><body><p>hello</p></body></html>');

        $registry = new EmailLayoutRegistry();
        $registry->addProvider($provider);

        $this->assertSame('<html><body><p>hello</p></body></html>', $registry->wrap('<p>hello</p>'));
    }

    // Only one app-wide branded layout is expected - the first registered provider wins
    public function testWrapKeepsFirstProviderResultWhenSeveralAreRegistered(): void
    {
        $providerA = $this->createStub(EmailLayoutProviderInterface::class);
        $providerA->method('wrap')->willReturn('from-a');

        $providerB = $this->createStub(EmailLayoutProviderInterface::class);
        $providerB->method('wrap')->willReturn('from-b');

        $registry = new EmailLayoutRegistry();
        $registry->addProvider($providerA);
        $registry->addProvider($providerB);

        $this->assertSame('from-a', $registry->wrap('<p>hello</p>'));
    }
}
