<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Command;

use c975L\UiBundle\Repository\BlockRepository;
use c975L\UiBundle\Service\VideoUploadDateFetcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'c975l:ui:video-upload-dates', description: 'Fills the upload date of video_iframe blocks that have none, asked from their platform')]
class VideoUploadDatesCommand extends Command
{
    public function __construct(
        private readonly BlockRepository $blockRepository,
        private readonly VideoUploadDateFetcher $videoUploadDateFetcher,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // A date typed by hand is never overwritten: the editor may know better than a platform that re-dated a re-upload
        $blocks = array_filter($this->blockRepository->findByKind('video_iframe'), static fn ($block): bool => empty($block->getData()['uploadDate']));

        $filled = 0;
        $missed = [];
        foreach ($blocks as $block) {
            $data = $block->getData();
            $date = $this->videoUploadDateFetcher->fetch($data['src'] ?? null);
            if (null === $date) {
                $missed[] = (string) ($data['src'] ?? '#' . $block->getId());

                continue;
            }

            $data['uploadDate'] = $date;
            $block->setData($data);
            ++$filled;
        }

        if ($filled > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf('%d vidéo(s) datée(s) sur %d.', $filled, \count($blocks)));

        // TikTok, a playlist or a platform that did not answer: the editor types the date in the block
        if ([] !== $missed) {
            $io->note(['Sans date, à saisir dans le block :', ...$missed]);
        }

        return Command::SUCCESS;
    }
}
