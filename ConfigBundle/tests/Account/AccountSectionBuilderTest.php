<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Account;

use c975L\ConfigBundle\Account\AccountSectionBuilder;
use c975L\ConfigBundle\Account\AccountSectionProviderInterface;
use c975L\ConfigBundle\Contract\UserInterface;
use PHPUnit\Framework\TestCase;

class AccountSectionBuilderTest extends TestCase
{
    // Every provider's sections in one list, the lowest position first whichever provider came first, with the optional keys filled
    public function testMergesAndOrdersTheSectionsOfEveryProvider(): void
    {
        $builder = new AccountSectionBuilder([
            $this->provider([['title' => 'label.orders', 'translation_domain' => 'payment', 'template' => 'orders.html.twig', 'position' => 20]]),
            $this->provider([['title' => 'label.credits', 'translation_domain' => 'purchasecredits', 'template' => 'credits.html.twig', 'context' => ['balance' => 3], 'position' => 10]]),
            $this->provider([]),
            $this->provider([['title' => 'label.shortcuts', 'translation_domain' => 'messages', 'template' => 'shortcuts.html.twig']]),
        ]);

        $sections = $builder->getSections($this->createStub(UserInterface::class));

        $this->assertSame(['label.shortcuts', 'label.credits', 'label.orders'], array_column($sections, 'title'));
        $this->assertSame([], $sections[0]['context']);
        $this->assertSame(0, $sections[0]['position']);
        $this->assertSame(['balance' => 3], $sections[1]['context']);
    }

    // The member being shown is the one handed to every provider, which reads its own data for them
    public function testHandsTheMemberToEveryProvider(): void
    {
        $user = $this->createStub(UserInterface::class);
        $provider = $this->createMock(AccountSectionProviderInterface::class);
        $provider->expects($this->once())->method('getAccountSections')->with($user)->willReturn([]);

        $this->assertSame([], new AccountSectionBuilder([$provider])->getSections($user));
    }

    // A provider answering the given sections
    /** @param list<array{title: string, translation_domain: string, template: string, context?: array<string, mixed>, position?: int}> $sections */
    private function provider(array $sections): AccountSectionProviderInterface
    {
        $provider = $this->createStub(AccountSectionProviderInterface::class);
        $provider->method('getAccountSections')->willReturn($sections);

        return $provider;
    }
}
