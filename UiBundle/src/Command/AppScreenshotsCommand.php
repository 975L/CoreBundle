<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Command;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Management\SiteGraphicImportProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Replaces the screens an installed web app shows in Android's install dialog (see PwaController) with the images of a folder, in their name order - what the Films workshop sends after photographing the site as an app. The site graphics import does the work, the role being a repeatable one whose whole pool it rebuilds
#[AsCommand(name: 'c975l:ui:app-screenshots', description: 'Replaces the app screenshots with the images of a folder')]
class AppScreenshotsCommand extends Command
{
    private const array EXTENSIONS = ['webp', 'png', 'jpg', 'jpeg'];

    public function __construct(
        private readonly SiteGraphicImportProvider $siteGraphicImportProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('folder', InputArgument::REQUIRED, 'Folder holding the screenshots');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $folder = rtrim((string) $input->getArgument('folder'), '/');

        // An empty or missing folder leaves the current screenshots in place, and says so without throwing
        $files = is_dir($folder) ? array_values(array_filter(
            scandir($folder),
            static fn (string $file): bool => in_array(strtolower(pathinfo($file, \PATHINFO_EXTENSION)), self::EXTENSIONS, true),
        )) : [];
        if ([] === $files) {
            $io->error(sprintf('Aucune image dans %s : captures inchangées.', $folder));

            return Command::FAILURE;
        }

        $result = $this->siteGraphicImportProvider->import(
            array_map(static fn (string $file): array => ['role' => Media::ROLE_APP_SCREENSHOT, 'file' => $file], $files),
            $folder,
        );
        $io->success(sprintf('%d capture(s) de l\'application en place.', $result['created']));

        return Command::SUCCESS;
    }
}
