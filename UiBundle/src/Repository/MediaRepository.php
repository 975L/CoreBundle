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

    // @return Media[] Rows holding a PDF, for the check that then looks for each one's thumbnail on disk (see PdfThumbnailHealthCheckProvider). Unlike findSvgCandidates() the extension alone decides: a PDF is never rewritten to another format on upload, and the mime type a browser sent for it is the unreliable half here (application/octet-stream from some clients)
    public function findPdfs(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.filename LIKE :pdfExtension')
            ->setParameter('pdfExtension', '%.pdf')
            ->orderBy('m.filename', 'ASC')
            ->getQuery()
            ->getResult();
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

    // @return Media[] Rows naming a file, for the check that then looks for each one on disk (see MediaFilesHealthCheckProvider). An empty string counts as naming none, a row created for its caption alone never having held a file
    public function findWithFilename(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.filename IS NOT NULL AND m.filename != :empty')
            ->setParameter('empty', '')
            ->orderBy('m.filename', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    // @return Media[] The rows hanging off a Block, with it already loaded - what MediaUsageRegistry::getBinnedOnlyMediaIds() is fed to decide which medias the library leaves out. The join is inner on purpose: only a media a block draws can be used by a binned page alone, so a library holding thousands of loose rows never reads them. The select is the point: BlockMediaUsageProvider reads each block's label and kind, and a lazy relation would fetch them one row at a time
    public function findAttachedToBlock(): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('b')
            ->innerJoin('m.block', 'b')
            ->getQuery()
            ->getResult()
        ;
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
