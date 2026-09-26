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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// A throwaway account for whatever drives the back office in a browser on a development copy - a screen recorder filming tutorials, an end-to-end run, a screenshot tool: opened with a password nobody types, closed once done. It lives no longer than that, so a local database sent back to production never carries it, and one imported from production never has to keep it
class TutorialAccount
{
    // A domain reserved for examples (RFC 2606): the account can never receive, nor be taken for, anyone's real address
    public const string DEFAULT_EMAIL = 'tutorial@example.com';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AdminUserCreator $adminUserCreator,
    ) {
    }

    // Opens the account with these roles and returns its fresh password: one left behind by an interrupted shoot is taken over rather than refused
    /** @param list<string> $roles */
    public function open(string $email, array $roles): string
    {
        $password = bin2hex(random_bytes(16));

        $user = $this->find($email);
        if (null === $user) {
            $this->adminUserCreator->create($email, $password, $roles);

            return $password;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles($roles);
        $user->setModification(new \DateTime());
        $this->entityManager->flush();

        return $password;
    }

    // Removes the account, false when there was none
    public function close(string $email): bool
    {
        $user = $this->find($email);
        if (null === $user) {
            return false;
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return true;
    }

    private function find(string $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    }
}
