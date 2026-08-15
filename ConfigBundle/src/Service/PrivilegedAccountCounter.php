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

// How many accounts hold a given role. A data-access layer of its own rather than a private method of the check that reads it (see IntrusionHealthCheckProvider), for the same reason a repository is one: what it answers is worth stubbing, and how it asks the database is not what that check is about.
class PrivilegedAccountCounter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Only the roles column is fetched and the match is made in php: "roles" is a json column, and the LIKE that would filter it in sql behaves differently on every engine - a bundle installed on databases it never chose cannot afford that. Roles are not hierarchical here either: a site whose admin role is granted through role_hierarchy rather than held outright is counted as zero, which is why the check reading this compares the number to its own previous run rather than to an expected one
    public function countHolding(string $role): int
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('u.roles')
            ->from(User::class, 'u')
            ->getQuery()
            ->getArrayResult();

        $count = 0;
        foreach ($rows as $row) {
            if (\in_array($role, (array) ($row['roles'] ?? []), true)) {
                ++$count;
            }
        }

        return $count;
    }
}
