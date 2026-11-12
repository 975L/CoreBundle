<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

class UserTest extends TestCase
{
    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();

        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testGetRolesDoesNotDuplicateRoleUser(): void
    {
        $user = new User()->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], array_values($user->getRoles()));
    }

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new User()->setEmail('user@example.test');

        $this->assertSame('user@example.test', $user->getUserIdentifier());
    }

    public function testGetUserIdentifierReturnsEmptyStringWhenEmailIsNull(): void
    {
        $this->assertSame('', new User()->getUserIdentifier());
    }

    public function testSerializeHashesPasswordInsteadOfStoringItInClear(): void
    {
        $user = new User()->setPassword('$argon2id$hashedPassword');

        $data = $user->__serialize();

        $this->assertSame(hash('crc32c', '$argon2id$hashedPassword'), $data["\0App\\Entity\\User\0password"]);
        $this->assertStringNotContainsString('$argon2id$hashedPassword', (string) $data["\0App\\Entity\\User\0password"]);
    }

    // The firewall compares the session's copy, its password reduced to a crc32c by __serialize(), with the account reloaded from the database
    public function testTheSessionCopyEqualsTheAccountItWasMadeFrom(): void
    {
        $user = $this->account();

        $this->assertTrue($this->sessionCopy($user)->isEqualTo($user));
    }

    // An account disabled from the back office loses its open sessions on their next request
    public function testADisabledAccountIsNoLongerTheSessionUser(): void
    {
        $user = $this->account();
        $session = $this->sessionCopy($user);

        $this->assertFalse($session->isEqualTo($user->setIsEnabled(false)));
    }

    // A new password, a role taken away or another address end the session too
    public function testAChangedPasswordRoleOrAddressIsNoLongerTheSessionUser(): void
    {
        $session = $this->sessionCopy($this->account());

        $this->assertFalse($session->isEqualTo($this->account()->setPassword('$argon2id$another')));
        $this->assertFalse($session->isEqualTo($this->account()->setRoles([])));
        $this->assertFalse($session->isEqualTo($this->account()->setEmail('other@example.test')));
    }

    // A role granted ends the session as surely as one taken away, the session's roles being the token's
    public function testAGrantedRoleIsNoLongerTheSessionUser(): void
    {
        $session = $this->sessionCopy($this->account());

        $this->assertFalse($session->isEqualTo($this->account()->setRoles(['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'])));
    }

    // The same roles in another order are still the same account
    public function testRolesInAnotherOrderAreStillTheSessionUser(): void
    {
        $session = $this->sessionCopy($this->account()->setRoles(['ROLE_ADMIN', 'ROLE_EDITOR']));

        $this->assertTrue($session->isEqualTo($this->account()->setRoles(['ROLE_EDITOR', 'ROLE_ADMIN'])));
    }

    // A user of another class is never the session's account
    public function testAForeignUserIsNoLongerTheSessionUser(): void
    {
        $this->assertFalse($this->account()->isEqualTo($this->createStub(UserInterface::class)));
    }

    public function testFluentSettersReturnSameInstance(): void
    {
        $user = new User();

        $this->assertSame($user, $user->setEmail('a@b.c'));
        $this->assertSame($user, $user->setPassword('pwd'));
        $this->assertSame($user, $user->setRoles([]));
        $this->assertSame($user, $user->setIsVerified(true));
        $this->assertSame($user, $user->setIsEnabled(true));
        $this->assertSame($user, $user->setCreation(new \DateTime()));
        $this->assertSame($user, $user->setModification(new \DateTime()));
    }

    public function testDefaultsAreNotVerifiedAndNotEnabled(): void
    {
        $user = new User();

        $this->assertFalse($user->isVerified());
        $this->assertFalse($user->isEnabled());
    }

    // An enabled admin account, as the database holds it
    private function account(): User
    {
        return new User()
            ->setEmail('user@example.test')
            ->setPassword('$argon2id$hashedPassword')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsEnabled(true);
    }

    // The account as the session stores it between two requests
    private function sessionCopy(User $user): User
    {
        return unserialize(serialize($user));
    }
}
