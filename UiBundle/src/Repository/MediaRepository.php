<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Repository;

use c975L\UiBundle\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    public function findOneByRole(string $role): ?Media
    {
        return $this->findOneBy(['role' => $role]);
    }

    // @return Media[] Every site-wide singleton role row (logo, favicon...) in one query - see MediaExtension::preloadSingletonRoles()
    public function findBySingletonRoles(): array
    {
        return $this->findBy(['role' => Media::getSingletonRoles()]);
    }

    // @return Media[] Rows whose stored file may still be SVG markup, for a check that then reads each one (see SiteBundle's SvgFontsHealthCheckProvider). Both the mime type and the extension are looked at: an icon role's file is renamed to the role's own .ico/.png on upload, so only the mime type it came in with says what it holds
    public function findSvgCandidates(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.mimeType LIKE :svgMimeType')
            ->orWhere('m.filename LIKE :svgExtension')
            ->setParameter('svgMimeType', 'image/svg%')
            ->setParameter('svgExtension', '%.svg')
            ->orderBy('m.filename', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Picks one row at random among all sharing a repeatable role (e.g. a pool of error images)
    public function findRandomByRole(string $role): ?Media
    {
        $ids = $this->createQueryBuilder('m')
            ->select('m.id')
            ->where('m.role = :role')
            ->setParameter('role', $role)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        if ([] === $ids) {
            return null;
        }

        return $this->find($ids[array_rand($ids)]);
    }

    // @return Media[] Rows whose width or height is still unset - what MediaDimensionsCommand backfills. An empty string counts as unset: the admin form (see MediaUploadType) submits one for a field left blank, where an untouched row holds null
    public function findWithoutDimensions(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.width IS NULL OR m.width = :empty OR m.height IS NULL OR m.height = :empty')
            ->setParameter('empty', '')
            ->getQuery()
            ->getResult()
        ;
    }
}
