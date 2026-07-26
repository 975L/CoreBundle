<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Command;

use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Service\ImageDimensionsReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Backfills the medias uploaded before VichImageResizeListener started recording their size. Only ever fills a row that has none, so a value an admin typed by hand (see MediaUploadType) survives - re-run it as often as needed
#[AsCommand(name: 'c975l:ui:media-dimensions', description: 'Fills the width/height of Media rows that have none, read from the file itself')]
class MediaDimensionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaRepository $mediaRepository,
        private readonly ImageDimensionsReader $imageDimensionsReader,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $medias = $this->mediaRepository->findWithoutDimensions();

        if (!$medias) {
            $io->success('Tous les médias ont déjà des dimensions.');

            return Command::SUCCESS;
        }

        $filled = 0;
        $skipped = [];
        foreach ($medias as $media) {
            // Non-image medias (pdf, audio, video) and files missing from disk simply have no dimensions to read - listed, not treated as failures
            $dimensions = $this->imageDimensionsReader->read($this->projectDir . '/public/' . $media->getFilename());
            if (null === $dimensions) {
                $skipped[] = (string) $media->getFilename();

                continue;
            }

            $media->setWidth((string) $dimensions['width']);
            $media->setHeight((string) $dimensions['height']);
            ++$filled;
        }

        if ($filled > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf('%d média(s) renseigné(s) sur %d.', $filled, count($medias)));

        if ($skipped) {
            $io->note(sprintf('%d média(s) sans dimensions lisibles :', count($skipped)));
            $io->listing($skipped);
        }

        return Command::SUCCESS;
    }
}
