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
use c975L\UiBundle\Controller\Management\MediaCrudController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Service\SvgTextDetector;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// Lists the SVG files the site serves and flags those still drawing their text with a font instead of with paths. The upload itself already warns (see UiBundle's SvgTextWarningListener), but only whoever was uploading saw it, and only from that day on - this is what surfaces the ones already in place, and the ones a content_import brought in with no session to flash to. Reads files off the disk rather than over http: they are this site's own, and an unreachable one is a different check's business (see SeoFilesHealthCheckProvider)
class SvgFontsHealthCheckProvider implements HealthCheckProviderInterface
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly SvgTextDetector $svgTextDetector,
        private readonly ConfigServiceInterface $configService,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getKind(): string
    {
        return 'svg-fonts';
    }

    public function runChecks(): array
    {
        $siteUrl = rtrim((string) $this->configService->get('site-url'), '/');

        $rows = [];
        foreach ($this->mediaRepository->findSvgCandidates() as $media) {
            $filename = (string) $media->getFilename();
            $absolutePath = $this->projectDir . '/public/' . $filename;

            // The query matches on the mime type and the extension, which both lie: an icon role's file was rasterized on upload and only carries the .ico/.png it was renamed to, and a row's file can be gone altogether. Only what is on disk decides
            if (!$this->svgTextDetector->isSvg($absolutePath)) {
                continue;
            }

            // The OK row is what lets a corrected file go back to green: results are kept per url and kind, so dropping it would leave the previous warning standing for good
            if (!$this->svgTextDetector->drawsText($absolutePath)) {
                $rows[] = $this->row($media, $siteUrl, HealthCheckResult::STATUS_OK, 'label.health_check_svg_fonts_ok');

                continue;
            }

            $fonts = $this->svgTextDetector->fontFamilies($absolutePath);
            $rows[] = $this->row(
                $media,
                $siteUrl,
                HealthCheckResult::STATUS_WARNING,
                'label.health_check_svg_fonts_not_vectorized',
                ['%fonts%' => [] === $fonts ? '' : ' (' . implode(', ', $fonts) . ')'],
                ['fonts' => $fonts],
            );
        }

        return $rows;
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
