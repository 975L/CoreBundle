<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Account;

use App\Entity\User;
use c975L\ConfigBundle\Contract\UserInterface;
use Doctrine\ORM\EntityManagerInterface;

// The profile part of a member's export: every column of the site's User but the password hash, read off its mapping as AccountProfileType reads it
class ProfileAccountDataProvider implements AccountDataProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Under "profile", column by column
    public function getAccountData(UserInterface $user): array
    {
        if (!$user instanceof User) {
            return [];
        }

        $metadata = $this->entityManager->getClassMetadata(User::class);
        $profile = [];
        foreach (array_diff($metadata->getFieldNames(), ['password']) as $field) {
            $profile[$field] = $metadata->getFieldValue($user, $field);
        }

        return ['profile' => $profile];
    }
}
