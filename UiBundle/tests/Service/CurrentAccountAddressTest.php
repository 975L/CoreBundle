<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use c975L\UiBundle\Service\CurrentAccountAddress;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

// Only the signed-in account's own address counts as proven
class CurrentAccountAddressTest extends TestCase
{
    private function address(?UserInterface $user): CurrentAccountAddress
    {
        return new CurrentAccountAddress($this->createConfiguredStub(Security::class, ['getUser' => $user]));
    }

    public function testNobodySignedInOwnsNothing(): void
    {
        $this->assertFalse($this->address(null)->owns('jean@example.org'));
    }

    public function testTheAccountOwnsItsAddressWhateverTheCaseAndSpaces(): void
    {
        $this->assertTrue($this->address(new UserStub('jean@example.org'))->owns('  Jean@Example.ORG '));
    }

    public function testTheAccountOwnsNoOtherAddress(): void
    {
        $address = $this->address(new UserStub('jean@example.org'));

        $this->assertFalse($address->owns('paul@example.org'));
        $this->assertFalse($address->owns(null));
        $this->assertFalse($address->owns(''));
    }

    // An account with no address of its own owns none, not even an empty one
    public function testAnAccountWithoutAnAddressOwnsNothing(): void
    {
        $this->assertFalse($this->address(new InMemoryUser('jean', null))->owns('jean'));
        $this->assertFalse($this->address(new UserStub(''))->owns(''));
    }

    // As the account holds it, what a form offers to save typing it
    public function testTheAddressIsThatOfTheAccountSignedIn(): void
    {
        $this->assertSame('Jean@example.org', $this->address(new UserStub('Jean@example.org'))->address());
    }

    public function testNoAddressForNobodyNorForAnAccountWithoutOne(): void
    {
        $this->assertNull($this->address(null)->address());
        $this->assertNull($this->address(new InMemoryUser('jean', null))->address());
        $this->assertNull($this->address(new UserStub(''))->address());
    }
}
