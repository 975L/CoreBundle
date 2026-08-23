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
use c975L\ConfigBundle\Model\OAuthIdentity;
use c975L\ConfigBundle\Model\OAuthResolution;
use c975L\ConfigBundle\Service\OAuthUserResolver;
use c975L\ConfigBundle\Service\UserCreationNotifier;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Repository\FormRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// The ways an OAuth identity can end: created, accepted, taken over, refused
class OAuthUserResolverTest extends TestCase
{
    private function createEntityManager(?User $existing, ?array &$persisted = null, ?int &$flushes = null): EntityManagerInterface
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->method('flush')->willReturnCallback(static function () use (&$flushes): void {
            ++$flushes;
        });

        return $entityManager;
    }

    private function createFormRepository(bool $registrationOpen = true, bool $seeded = true): FormRepository
    {
        $formRepository = $this->createStub(FormRepository::class);
        $formRepository->method('findOneBy')->willReturn($seeded ? new Form()->setEnabled($registrationOpen) : null);

        return $formRepository;
    }

    private function createNotifier(?array &$notified = null): UserCreationNotifier
    {
        $notifier = $this->createStub(UserCreationNotifier::class);
        $notifier->method('notify')->willReturnCallback(static function (object $user) use (&$notified): bool {
            $notified[] = $user;

            return true;
        });

        return $notifier;
    }

    // Hashes to something recognisable, and keeps what it was given so a test can tell two random passwords apart
    private function createPasswordHasher(?array &$hashed = null): UserPasswordHasherInterface
    {
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturnCallback(static function (object $user, string $plain) use (&$hashed): string {
            $hashed[] = $plain;

            return 'hashed-' . $plain;
        });

        return $passwordHasher;
    }

    public function testAnAddressNobodyHoldsYetCreatesAVerifiedAndEnabledAccount(): void
    {
        $persisted = [];
        $flushes = 0;
        $resolver = new OAuthUserResolver($this->createEntityManager(null, $persisted, $flushes), $this->createPasswordHasher(), $this->createNotifier(), $this->createFormRepository());

        $resolution = $resolver->resolve(new OAuthIdentity('visitor@example.test', true));

        $this->assertInstanceOf(User::class, $resolution->user);
        $this->assertSame([$resolution->user], $persisted);
        $this->assertSame(1, $flushes);
        $this->assertSame('visitor@example.test', $resolution->user->getEmail());
        // UserChecker refuses a disabled account before even checking a password: created any other way, the account couldn't log in on the very request that created it
        $this->assertTrue($resolution->user->isVerified());
        $this->assertTrue($resolution->user->isEnabled());
        $this->assertNotNull($resolution->user->getCreation());
        $this->assertFalse($resolution->passwordReset);
    }

    // Nobody knows it, the owner included - "forgot password" is how they give themselves one. Filled rather than left null, which would mean a migration on every site plus a null to guard in the login form and in the password reset
    public function testACreatedAccountCarriesARandomPassword(): void
    {
        $hashed = [];
        $resolver = new OAuthUserResolver($this->createEntityManager(null), $this->createPasswordHasher($hashed), $this->createNotifier(), $this->createFormRepository());

        $first = $resolver->resolve(new OAuthIdentity('visitor@example.test', true));
        $second = $resolver->resolve(new OAuthIdentity('other@example.test', true));

        $this->assertCount(2, $hashed);
        $this->assertNotSame($hashed[0], $hashed[1]);
        $this->assertSame('hashed-' . $hashed[0], $first->user?->getPassword());
        $this->assertSame('hashed-' . $hashed[1], $second->user?->getPassword());
    }

    // An account is an account whichever door it came through: the owner hears about a Google sign-up exactly as they hear about a registration (see UserRegistrar)
    public function testACreatedAccountIsAnnouncedToTheSiteOwner(): void
    {
        $notified = [];
        $resolver = new OAuthUserResolver($this->createEntityManager(null), $this->createPasswordHasher(), $this->createNotifier($notified), $this->createFormRepository());

        $resolution = $resolver->resolve(new OAuthIdentity('visitor@example.test', true));

        $this->assertSame([$resolution->user], $notified);
    }

    // Nothing was created, so there is nothing to announce - the owner already heard about that account when it was registered
    public function testAnExistingAccountIsNotAnnouncedAgain(): void
    {
        $existing = new User()->setEmail('visitor@example.test')->setPassword('chosen-by-its-owner')->setIsEnabled(true);

        $notified = [];
        $resolver = new OAuthUserResolver($this->createEntityManager($existing), $this->createPasswordHasher(), $this->createNotifier($notified), $this->createFormRepository());

        $resolver->resolve(new OAuthIdentity('visitor@example.test', true));

        $this->assertSame([], $notified);
    }

    // Both ways in keep working: the account is its owner's, and signing in through a provider is not a reason to touch what it holds
    public function testAConfirmedAccountIsLoggedInUntouched(): void
    {
        $existing = new User()
            ->setEmail('visitor@example.test')
            ->setPassword('chosen-by-its-owner')
            ->setRoles(['ROLE_EDITOR'])
            ->setIsVerified(true)
            ->setIsEnabled(true);

        $flushes = 0;
        $resolver = new OAuthUserResolver($this->createEntityManager($existing, flushes: $flushes), $this->createPasswordHasher(), $this->createNotifier(), $this->createFormRepository());

        $resolution = $resolver->resolve(new OAuthIdentity('visitor@example.test', true));

        $this->assertSame($existing, $resolution->user);
        $this->assertSame('chosen-by-its-owner', $existing->getPassword());
        $this->assertContains('ROLE_EDITOR', $existing->getRoles());
        $this->assertFalse($resolution->passwordReset);
        $this->assertSame(0, $flushes);
    }

    // Pre-account hijacking: anyone can register someone else's address at the public form without ever confirming it. Enabling that account as it stands would hand it to its real owner with the password whoever created it chose still working
    public function testAnAccountThatNeverConfirmedItsEmailLosesItsPasswordBeforeBeingEnabled(): void
    {
        $squatted = new User()
            ->setEmail('visitor@example.test')
            ->setPassword('chosen-by-whoever-registered-it')
            ->setIsVerified(false)
            ->setIsEnabled(false);

        $flushes = 0;
        $resolver = new OAuthUserResolver($this->createEntityManager($squatted, flushes: $flushes), $this->createPasswordHasher(), $this->createNotifier(), $this->createFormRepository());

        $resolution = $resolver->resolve(new OAuthIdentity('visitor@example.test', true));

        $this->assertSame($squatted, $resolution->user);
        $this->assertNotSame('chosen-by-whoever-registered-it', $squatted->getPassword());
        $this->assertTrue($squatted->isVerified());
        $this->assertTrue($squatted->isEnabled());
        $this->assertSame(1, $flushes);
        // Said to the visitor, whose former password stopped working
        $this->assertTrue($resolution->passwordReset);
    }

    // A ban is the one way an account confirmed once loses "enabled", and a provider's door has to hold it: taking it over would have re-enabled it and handed it back
    public function testABannedAccountIsRefusedWithoutTouchingAnything(): void
    {
        $banned = new User()
            ->setEmail('visitor@example.test')
            ->setPassword('chosen-by-its-owner')
            ->setIsVerified(true)
            ->setIsEnabled(false);

        $persisted = [];
        $flushes = 0;
        $resolver = new OAuthUserResolver($this->createEntityManager($banned, $persisted, $flushes), $this->createPasswordHasher(), $this->createNotifier(), $this->createFormRepository());

        $resolution = $resolver->resolve(new OAuthIdentity('visitor@example.test', true));

        $this->assertNull($resolution->user);
        $this->assertSame(OAuthResolution::REASON_ACCOUNT_DISABLED, $resolution->reason);
        $this->assertSame([], $persisted);
        $this->assertSame(0, $flushes);
        $this->assertSame('chosen-by-its-owner', $banned->getPassword());
        $this->assertFalse($banned->isEnabled());
    }

    // Unchecking the "register" Form closes registration to every door: the OAuth one was letting accounts in behind it
    public function testAClosedRegistrationRefusesToCreateAnAccount(): void
    {
        $persisted = [];
        $resolver = new OAuthUserResolver($this->createEntityManager(null, $persisted), $this->createPasswordHasher(), $this->createNotifier(), $this->createFormRepository(registrationOpen: false));

        $resolution = $resolver->resolve(new OAuthIdentity('visitor@example.test', true));

        $this->assertNull($resolution->user);
        $this->assertSame(OAuthResolution::REASON_REGISTRATION_CLOSED, $resolution->reason);
        $this->assertSame([], $persisted);
    }

    // Closed to new accounts, open to the ones that exist: somebody who registered while it was open goes on signing in
    public function testAClosedRegistrationStillLogsAnExistingAccountIn(): void
    {
        $existing = new User()->setEmail('visitor@example.test')->setPassword('chosen-by-its-owner')->setIsEnabled(true);
        $resolver = new OAuthUserResolver($this->createEntityManager($existing), $this->createPasswordHasher(), $this->createNotifier(), $this->createFormRepository(registrationOpen: false));

        $this->assertSame($existing, $resolver->resolve(new OAuthIdentity('visitor@example.test', true))->user);
    }

    // The whole safety of linking accounts by email alone: without this, anyone could declare someone else's address at a provider that doesn't check it and be handed their account
    public function testAnUnverifiedAddressIsRefusedWithoutTouchingAnything(): void
    {
        $existing = new User()->setEmail('visitor@example.test')->setPassword('untouched')->setIsEnabled(true);

        $persisted = [];
        $flushes = 0;
        $resolver = new OAuthUserResolver($this->createEntityManager($existing, $persisted, $flushes), $this->createPasswordHasher(), $this->createNotifier(), $this->createFormRepository());

        $resolution = $resolver->resolve(new OAuthIdentity('visitor@example.test', false));

        $this->assertNull($resolution->user);
        $this->assertSame(OAuthResolution::REASON_EMAIL_NOT_VERIFIED, $resolution->reason);
        $this->assertSame([], $persisted);
        $this->assertSame(0, $flushes);
        $this->assertSame('untouched', $existing->getPassword());
    }
}
