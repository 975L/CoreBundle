<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Command;

use c975L\UiBundle\Listener\SvgSanitizeListener;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Cleans the SVGs stored under public/medias before SvgSanitizeListener existed, as an upload now would: every bundle stores its files there. A file already clean is left alone and one that does not parse is only listed, never removed, so re-run it as often as needed
#[AsCommand(name: 'c975l:ui:media-sanitize-svg', description: 'Strips the stored SVGs of their scripts, event handlers and remote references')]
class MediaSanitizeSvgCommand extends Command
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Lists the files that would be rewritten, without touching them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $rewritten = [];
        $unreadable = [];

        foreach ($this->svgFiles() as $path => $filename) {
            $svg = (string) file_get_contents($path);
            $sanitized = SvgSanitizeListener::sanitize($svg);
            if (null === $sanitized) {
                $unreadable[] = $filename;
            } elseif ($sanitized !== $svg) {
                $rewritten[] = $filename;
                if (!$dryRun) {
                    file_put_contents($path, $sanitized);
                }
            }
        }

        // Left where they are: a file an admin can still replace, rather than an image gone from a page
        if ([] !== $unreadable) {
            $io->warning(\sprintf('%d SVG illisible(s), à remplacer : %s', \count($unreadable), implode(', ', $unreadable)));
        }

        if ([] === $rewritten) {
            $io->success('Tous les SVG sont déjà nettoyés.');

            return Command::SUCCESS;
        }

        $io->listing($rewritten);
        $io->success(\sprintf($dryRun ? '%d SVG à nettoyer.' : '%d SVG nettoyé(s).', \count($rewritten)));

        return Command::SUCCESS;
    }

    // Every SVG under public/medias, by its name or by its bytes, keyed by its absolute path
    /** @return array<string, string> */
    private function svgFiles(): array
    {
        $root = $this->projectDir . '/public/';
        if (!is_dir($root . 'medias')) {
            return [];
        }

        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . 'medias', \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && SvgSanitizeListener::isSvg($file->getPathname())) {
                $files[$file->getPathname()] = substr($file->getPathname(), \strlen($root));
            }
        }
        ksort($files);

        return $files;
    }
}
