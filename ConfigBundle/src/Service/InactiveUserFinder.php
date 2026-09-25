<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Contract\UserInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

// The inactive accounts c975l:config:users-cleanup acts on, queried on UserInterface which Doctrine resolves to the app's own User
class InactiveUserFinder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Whether the app's User tracks its inactivity at all, a site that hasn't re-scaffolded yet having nothing to find
    public function isSupported(): bool
    {
        return is_a($this->entityManager->getClassMetadata(UserInterface::class)->getName(), InactivityAwareInterface::class, true);
    }

    // Starts the clock of the accounts created before it existed, so they get the whole period rather than being skipped forever by the NULL
    public function startMissingClocks(): int
    {
        return $this->entityManager->createQueryBuilder()
            ->update(UserInterface::class, 'u')
            ->set('u.lastLogin', ':now')
            ->where('u.lastLogin IS NULL')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    // The enabled accounts unused since the given date and not warned yet, a disabled one being unable to log in anyway
    /** @return list<InactivityAwareInterface> */
    public function findToNotify(\DateTimeInterface $lastLoginBefore): array
    {
        return $this->active()
            ->andWhere('u.lastLogin < :lastLoginBefore')
            ->andWhere('u.isEnabled = true')
            ->andWhere('u.inactivityNoticeSentAt IS NULL')
            ->setParameter('lastLoginBefore', $lastLoginBefore)
            ->getQuery()
            ->getResult();
    }

    // The accounts unused since the given date and warned before the other one, or disabled and so never warned
    /** @return list<InactivityAwareInterface> */
    public function findToAnonymize(\DateTimeInterface $lastLoginBefore, \DateTimeInterface $noticeSentBefore): array
    {
        return $this->active()
            ->andWhere('u.lastLogin < :lastLoginBefore')
            ->andWhere('u.inactivityNoticeSentAt < :noticeSentBefore OR u.isEnabled = false')
            ->setParameter('lastLoginBefore', $lastLoginBefore)
            ->setParameter('noticeSentBefore', $noticeSentBefore)
            ->getQuery()
            ->getResult();
    }

    // Every account not anonymized yet, whatever its state
    private function active(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(UserInterface::class, 'u')
            ->where('u.email NOT LIKE :anonymized')
            ->setParameter('anonymized', '%@' . InactivityAwareInterface::ANONYMIZED_DOMAIN);
    }
}
