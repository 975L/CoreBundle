<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use App\Entity\User;
use c975L\ConfigBundle\Model\OAuthIdentity;
use c975L\ConfigBundle\Model\OAuthResolution;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Repository\FormRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// Turns "the provider says this address just signed in" into the account to log in - creating it, accepting it, or taking it over, and refusing outright when the address wasn't vouched for.
//
// Accounts are linked by email alone: no per-provider column, hence no migration on any site already running. What makes that safe is the refusal below - an address the provider doesn't mark as verified never reaches an account, since anyone could otherwise declare someone else's address at a lax provider and be handed their account.
//
// Builds App\Entity\User directly, as AdminUserCreator does and for the same reason: this bundle's own scaffold ships that entity (see scaffold/src/Entity/User.php), and the concrete class is what Doctrine persists
class OAuthUserResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserCreationNotifier $userCreationNotifier,
        private readonly FormRepository $formRepository,
    ) {
    }

    public function resolve(OAuthIdentity $identity): OAuthResolution
    {
        // The condition holding everything else up, checked before anything is read or written
        if (!$identity->emailVerified) {
            return OAuthResolution::refused(OAuthResolution::REASON_EMAIL_NOT_VERIFIED);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $identity->email]);

        if (!$user instanceof User) {
            // A site that closed its registration form closed it to every door, this one included - an account that already exists goes on signing in below
            if (!$this->isRegistrationOpen()) {
                return OAuthResolution::refused(OAuthResolution::REASON_REGISTRATION_CLOSED);
            }

            return new OAuthResolution($this->create($identity->email));
        }

        // Confirmed once and disabled since: that is a ban, the only way an account loses "enabled" after it verified its address (isVerified is the confirmation e-mail's own answer and no screen edits it, isEnabled is what the Users screen switches off). Refused here rather than left to UserChecker, which would say it after the password was already replaced below
        if ($user->isVerified() && !$user->isEnabled()) {
            return OAuthResolution::refused(OAuthResolution::REASON_ACCOUNT_DISABLED);
        }

        // An account that confirmed its email is its owner's: it's logged in as it stands, its password and roles untouched, so both ways in keep working
        if ($user->isEnabled()) {
            return new OAuthResolution($user);
        }

        return new OAuthResolution($this->takeOver($user), true);
    }

    // Registered, never confirmed - which is exactly the account anyone can create with someone else's address at the public registration form. It's blocked today (never having confirmed, it is disabled, and UserChecker refuses that before even checking the password), and enabling it as it stands would hand it over along with the password whoever created it chose.
    //
    // The provider just proved the address, so it becomes the only authority on that account: the existing password is replaced by one nobody holds, and the real owner gives themselves a new one through "forgot password" if they want one
    private function takeOver(User $user): User
    {
        $user->setPassword($this->hashRandomPassword($user));
        $user->setIsVerified(true);
        $user->setIsEnabled(true);
        $user->setModification(new \DateTime());

        $this->entityManager->flush();

        return $user;
    }

    // The address is verified and belongs to nobody here yet. Verified and enabled straight away: the provider did what the confirmation email exists to do, and UserChecker would otherwise refuse the account on the very login that created it
    private function create(string $email): User
    {
        $now = new \DateTime();

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->hashRandomPassword($user));
        $user->setIsVerified(true);
        $user->setIsEnabled(true);
        $user->setCreation($now);
        $user->setModification($now);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Same notification any registration sends (see UserRegistrar): an account is an account whichever door it came through, and the owner has as much reason to hear about this one. Its result is dropped on purpose, a notification the visitor knows nothing about having no business turning their sign-in into a failure
        $this->userCreationNotifier->notify($user);

        return $user;
    }

    // The "register" Form's own switch, read by its action rather than by its name, which is editable from the Forms screen (see UserFormSeeder::REGISTER_ACTION and RegistrationStatusProvider, which answer the same question elsewhere). A form no site has seeded yet counts as closed, as it does there
    private function isRegistrationOpen(): bool
    {
        $form = $this->formRepository->findOneBy(['action' => UserFormSeeder::REGISTER_ACTION]);

        return $form instanceof Form && $form->isEnabled();
    }

    // Fills the column rather than making it nullable: the entity is copied into every site (scaffold), so a nullable password would mean a migration everywhere plus a null to guard in the login form and in the password reset. A random one nobody holds - not even the account's owner, who reaches it through "forgot password" - costs neither
    private function hashRandomPassword(User $user): string
    {
        return $this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32)));
    }
}
