<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Repository;

use c975L\ConfigBundle\Entity\Config;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository<\c975L\ConfigBundle\Entity\Config>
 */
class ConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Config::class);
    }

    // Declared explicitly rather than left to Doctrine's magic finder, which static analysis cannot see and which Doctrine ORM will drop
    public function findOneBySlug(string $slug): ?Config
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    // Returns every slug stored in database, to be compared with what the configs*.json files declare
    public function findAllSlugs(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.slug')
            ->orderBy('c.slug', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    // Returns configs flagged with a severity whose value is still empty, i.e. requiring admin attention
    public function findRequiringAttention(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.severity IS NOT NULL')
            ->andWhere("c.value IS NULL OR c.value = ''")
            ->orderBy('c.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Every sensitive config actually holding a value, which is what tells an entry filled but unreadable from one simply left empty (see ConfigAlertProvider)
    public function findSensitiveWithValue(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isSensitive = :sensitive')
            ->andWhere("c.value IS NOT NULL AND c.value != ''")
            ->setParameter('sensitive', true)
            ->orderBy('c.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Returns every config belonging to the given group (e.g. Config::GROUP_THEME), sorted by label
    public function findByGroup(string $group): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.group = :group')
            ->setParameter('group', $group)
            ->orderBy('c.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Returns every config whose slug starts with the given prefix, whatever the group it is displayed in - what UiBundle's ThemeVariablesCssListener compiles into site-theme.css from the "theme-" ones, a satellite bundle being free to declare its own colors in its own group
    public function findBySlugPrefix(string $prefix): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.slug LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('c.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Config count per group, respecting the same "sensitive"/"restricted" visibility rules as ConfigCrudController's own index query - backs its intermediate "pick a group" screen. Reads live DISTINCT group values rather than the fixed Config::GROUPS enum, so a group only present in data (e.g. a bundle's configs.json using a value not yet added to that enum) still shows up
    public function countsByGroup(bool $isSensitive, bool $includeRestricted): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.group AS grp, COUNT(c.id) AS itemCount')
            ->andWhere('c.group IS NOT NULL')
            ->andWhere('c.isSensitive = :isSensitive')
            ->setParameter('isSensitive', $isSensitive)
            ->groupBy('c.group')
            ->orderBy('c.group', 'ASC')
        ;

        if (!$includeRestricted) {
            $qb->andWhere('c.isRestricted IS NULL OR c.isRestricted = :isRestricted')
                ->setParameter('isRestricted', false);
        }

        $rows = $qb->getQuery()->getResult();

        return array_combine(array_column($rows, 'grp'), array_column($rows, 'itemCount'));
    }
}
