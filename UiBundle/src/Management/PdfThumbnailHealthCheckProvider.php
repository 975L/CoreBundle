<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\EnvironmentProbe;
use c975L\UiBundle\Controller\Management\MediaCrudController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Listener\VichPdfThumbnailListener;
use c975L\UiBundle\Repository\MediaRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// Lists the PDF medias whose thumbnail was never produced. Its whole reason for existing is that failing to produce one is silent by design (see VichPdfThumbnailListener): the upload succeeds, the document downloads fine, and the block falls back to a placeholder - so nothing anywhere says the site is missing something until someone happens to look at the page.
//
// Says why, not just how many: whether Ghostscript answers and whether exec() can be called at all are read here (see EnvironmentProbe), because those two turn "this document has no thumbnail" into either "re-save it" or "the server cannot do it for any document, and no amount of re-saving will help". They are read per run rather than stored, a host being free to withdraw either between two runs without anything on the site changing.
class PdfThumbnailHealthCheckProvider implements HealthCheckProviderInterface
{
    // Named here rather than restated wherever a row of this kind is picked out
    public const string KIND = 'pdf-thumbnail';

    // What VichPdfThumbnailListener shells out to. Named once, so the row saying it is missing and the listener needing it cannot come to mean two different binaries
    private const string THUMBNAIL_BINARY = 'gs';

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly EnvironmentProbe $environmentProbe,
        private readonly ConfigServiceInterface $configService,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    public function runChecks(): array
    {
        $medias = $this->mediaRepository->findPdfs();

        // A site with no PDF at all has nothing to report: whether the server could make a thumbnail is not a defect until something needs one
        if ([] === $medias) {
            return [];
        }

        $siteUrl = rtrim((string) $this->configService->get('site-url'), '/');
        $canGenerate = $this->canGenerate();

        $rows = [];
        foreach ($medias as $media) {
            $filename = (string) $media->getFilename();

            // A row with no filename is a fixture or a placeholder media, never a document a visitor can reach
            if ('' === $filename) {
                continue;
            }

            // The very path DocumentExtension looks for when rendering the block, through the listener's own method - anything else here would report on a file the site never asks for
            $thumbnail = VichPdfThumbnailListener::toWebpPath($filename);

            // The OK row is what lets a fixed media go back to green: results are kept per url and kind, so dropping it would leave the previous warning standing for good
            if (file_exists($this->projectDir . '/public/' . $thumbnail)) {
                $rows[] = $this->row($media, $siteUrl, HealthCheckResult::STATUS_OK, 'label.health_check_pdf_thumbnail_ok');

                continue;
            }

            $rows[] = $this->row(
                $media,
                $siteUrl,
                HealthCheckResult::STATUS_WARNING,
                $canGenerate
                    ? 'label.health_check_pdf_thumbnail_missing'
                    : 'label.health_check_pdf_thumbnail_unavailable',
                [],
                [
                    'thumbnail' => $thumbnail,
                    'sapi' => $this->environmentProbe->getSapi(),
                    'exec' => $this->environmentProbe->canExec(),
                    'ghostscript' => $this->environmentProbe->getBinaryVersion(self::THUMBNAIL_BINARY),
                ],
            );
        }

        return $rows;
    }

    // Both halves of what the listener needs, in the order it needs them: without exec() the binary is unreachable whether it is installed or not, which is why its version is never even asked for then (see EnvironmentProbe)
    private function canGenerate(): bool
    {
        return $this->environmentProbe->canExec()
            && null !== $this->environmentProbe->getBinaryVersion(self::THUMBNAIL_BINARY);
    }

    private function row(Media $media, string $siteUrl, string $status, string $translationId, array $params = [], array $details = []): array
    {
        $filename = (string) $media->getFilename();

        return [
            'url' => $siteUrl . '/' . $filename,
            'label' => $media->getName() ?: $filename,
            'status' => $status,
            'summary' => $this->translator->trans($translationId, $params + ['%file%' => $filename], 'ui'),
            'details' => $details,
            'editUrl' => $this->adminUrlGenerator
                ->unsetAll()
                ->setController(MediaCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($media->getId())
                ->generateUrl(),
        ];
    }
}
