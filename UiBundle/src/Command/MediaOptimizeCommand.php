<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Command;

use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Listener\VichImageResizeListener;
use c975L\UiBundle\Service\BlockCacheInvalidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Vich\UploaderBundle\Mapping\PropertyMappingFactoryInterface;

// Rewrites the stored images of every entity opting into the resize pipeline, whatever its bundle, that an upload would no longer leave as they are: a jpeg or png under a ".webp" name, or a picture wider than its entity asks for. The file keeps its name, so no row and no link changes - re-run it as often as needed, a file already in line being left alone
#[AsCommand(name: 'c975l:ui:media-optimize', description: 'Converts to webp and resizes the stored images an upload would no longer leave as they are')]
class MediaOptimizeCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PropertyMappingFactoryInterface $propertyMappingFactory,
        private readonly VichImageResizeListener $vichImageResizeListener,
        private readonly BlockCacheInvalidator $blockCacheInvalidator,
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
        $before = 0;
        $after = 0;

        foreach ($this->resizableClasses() as $class) {
            foreach ($this->entityManager->getRepository($class)->findAll() as $entity) {
                // A single-table root hands back its subclasses' rows too, each of which gets its own pass under its own class
                if ($entity::class !== $class) {
                    continue;
                }

                foreach ($this->propertyMappingFactory->fromObject($entity) as $mapping) {
                    $filename = (string) $mapping->getFileName($entity);
                    $path = $this->projectDir . '/public/' . $filename;
                    if ('' === $filename || !is_file($path)) {
                        continue;
                    }

                    $size = (int) filesize($path);

                    // One unreadable file must not stop the run, nor end it on an exception the production console would report as critical
                    try {
                        if ($this->vichImageResizeListener->optimizeStoredImage($entity, $path, !$dryRun)) {
                            $rewritten[] = $filename;
                            $before += $size;
                            clearstatcache(true, $path);
                            $after += $dryRun ? $size : (int) filesize($path);
                        }
                    } catch (\Throwable $e) {
                        $io->warning(sprintf('%s : %s', $filename, $e->getMessage()));
                    }
                }
            }

            if (!$dryRun) {
                $this->entityManager->flush();
            }
            $this->entityManager->clear();
        }

        if ([] === $rewritten) {
            $io->success('Toutes les images sont déjà optimisées.');

            return Command::SUCCESS;
        }

        $io->listing($rewritten);

        if ($dryRun) {
            $io->note(sprintf('%d image(s) à réécrire (%d Ko).', count($rewritten), intdiv($before, 1024)));

            return Command::SUCCESS;
        }

        // The cached blocks still carry the width and height of the files they were rendered with
        $this->blockCacheInvalidator->invalidateAll();
        $io->success(sprintf('%d image(s) réécrite(s) : %d Ko -> %d Ko.', count($rewritten), intdiv($before, 1024), intdiv($after, 1024)));

        return Command::SUCCESS;
    }

    // The concrete entity classes opting into the pipeline, from every bundle the application loads
    /** @return array<int, class-string<VichImageResizableInterface>> */
    private function resizableClasses(): array
    {
        $classes = [];
        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $reflection = $metadata->getReflectionClass();
            if (!$metadata->isMappedSuperclass && !$reflection->isAbstract() && $reflection->implementsInterface(VichImageResizableInterface::class)) {
                $classes[] = $metadata->getName();
            }
        }

        return $classes;
    }
}
