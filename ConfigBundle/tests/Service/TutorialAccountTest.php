<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use App\Entity\User;
use c975L\ConfigBundle\Service\AdminUserCreator;
use c975L\ConfigBundle\Service\TutorialAccount;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TutorialAccountTest extends TestCase
{
    // A mock when the test sets expectations on it, a stub otherwise
    private function createEntityManager(?User $existing, bool $mock = false): EntityManagerInterface
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $entityManager = $mock ? $this->createMock(EntityManagerInterface::class) : $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        return $entityManager;
    }

    private function createPasswordHasher(): UserPasswordHasherInterface
    {
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturnCallback(static fn (User $user, string $plain): string => 'hashed-' . $plain);

        return $passwordHasher;
    }

    // A first shoot creates the account at the level asked for, with the password it hands back
    public function testOpenCreatesTheAccountWithTheRolesAsked(): void
    {
        $adminUserCreator = $this->createMock(AdminUserCreator::class);
        $adminUserCreator->expects($this->once())
            ->method('create')
            ->with(TutorialAccount::DEFAULT_EMAIL, $this->matchesRegularExpression('/^[0-9a-f]{32}$/'), ['ROLE_CONTRIBUTOR']);

        $account = new TutorialAccount($this->createEntityManager(null), $this->createPasswordHasher(), $adminUserCreator);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $account->open(TutorialAccount::DEFAULT_EMAIL, ['ROLE_CONTRIBUTOR']));
    }

    // An account left behind by an interrupted shoot is taken over: new password, new roles, never a second account
    public function testOpenTakesOverAnAccountLeftBehind(): void
    {
        $user = new User();
        $user->setEmail(TutorialAccount::DEFAULT_EMAIL);
        $user->setRoles(['ROLE_ADMIN']);

        $adminUserCreator = $this->createMock(AdminUserCreator::class);
        $adminUserCreator->expects($this->never())->method('create');

        $entityManager = $this->createEntityManager($user, true);
        $entityManager->expects($this->once())->method('flush');

        $password = new TutorialAccount($entityManager, $this->createPasswordHasher(), $adminUserCreator)->open(TutorialAccount::DEFAULT_EMAIL, ['ROLE_EDITOR']);

        $this->assertSame('hashed-' . $password, $user->getPassword());
        $this->assertContains('ROLE_EDITOR', $user->getRoles());
        $this->assertNotContains('ROLE_ADMIN', $user->getRoles());
    }

    // Two passwords in a row are never the same: nothing about a past shoot opens the next one
    public function testEachOpeningHasAPasswordOfItsOwn(): void
    {
        $account = new TutorialAccount($this->createEntityManager(null), $this->createPasswordHasher(), $this->createStub(AdminUserCreator::class));

        $this->assertNotSame($account->open(TutorialAccount::DEFAULT_EMAIL, ['ROLE_CONTRIBUTOR']), $account->open(TutorialAccount::DEFAULT_EMAIL, ['ROLE_CONTRIBUTOR']));
    }

    public function testCloseRemovesTheAccount(): void
    {
        $user = new User();

        $entityManager = $this->createEntityManager($user, true);
        $entityManager->expects($this->once())->method('remove')->with($user);
        $entityManager->expects($this->once())->method('flush');

        $this->assertTrue(new TutorialAccount($entityManager, $this->createPasswordHasher(), $this->createStub(AdminUserCreator::class))->close(TutorialAccount::DEFAULT_EMAIL));
    }

    public function testCloseWithoutAccountDoesNothing(): void
    {
        $entityManager = $this->createEntityManager(null, true);
        $entityManager->expects($this->never())->method('remove');

        $this->assertFalse(new TutorialAccount($entityManager, $this->createPasswordHasher(), $this->createStub(AdminUserCreator::class))->close(TutorialAccount::DEFAULT_EMAIL));
    }
}
