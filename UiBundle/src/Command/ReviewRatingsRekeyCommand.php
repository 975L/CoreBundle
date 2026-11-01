<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Command;

use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Repository\ReviewRepository;
use c975L\UiBundle\Service\ReviewService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// To run once per site after v1.85.0: the unverified reviews published before it have their score under their author's address, where anyone typing that address could overwrite it. Run again it finds nothing to do
#[AsCommand(name: 'c975l:ui:review-ratings-rekey', description: 'Moves the scores of the unverified reviews already published from their address to a key per review')]
class ReviewRatingsRekeyCommand extends Command
{
    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly ReviewService $reviewService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Says how many reviews would move, and writes nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $moved = 0;
        foreach ($this->oneReviewPerAddress() as $review) {
            $moved += $this->reviewService->rekeyLegacyRating($review, $dryRun);
        }

        $io->success(sprintf($dryRun ? '%d review(s) would get a score of their own.' : '%d review(s) got a score of their own.', $moved));

        return Command::SUCCESS;
    }

    // ReviewService deals with an address and an owner at once: asked again for the next review of the same pair, a dry run would count them twice
    /** @return Review[] */
    private function oneReviewPerAddress(): array
    {
        $reviews = [];
        foreach ($this->reviewRepository->findPublishedUnverifiedRated() as $review) {
            $reviews[$review->getOwnerType() . '|' . $review->getOwnerId() . '|' . strtolower(trim((string) $review->getAuthorEmail()))] ??= $review;
        }

        return array_values($reviews);
    }
}
