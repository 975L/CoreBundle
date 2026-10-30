<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// The file a demo dataset hands VichUploader for one of the site's placeholder pictures (see PlaceholderMediaRegistry), shared by every DemoFixtureProviderInterface so the rule lives in one place
class DemoFixtureMediaCopier
{
    // The copies made during this request or command, kept to be swept once it is over
    /** @var list<string> */
    private array $temporaryPaths = [];

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // VichUploader moves the file it is handed, so it gets a copy - the placeholder is read by every other showcase and would be gone after the first load - and a ReplacingFile rather than a plain File, which UploadHandler::hasUploadedFile() ignores silently, writing the row with no file name. Null when the site no longer holds the file
    public function copy(string $publicPath): ?ReplacingFile
    {
        $source = $this->projectDir . '/public/' . $publicPath;

        if (!is_file($source)) {
            return null;
        }

        $target = sys_get_temp_dir() . '/c975l-demo-' . uniqid() . '-' . basename($publicPath);

        if (!copy($source, $target)) {
            return null;
        }

        $this->temporaryPaths[] = $target;

        return new ReplacingFile($target, true, true, true);
    }

    // A load that failed or stopped half-way leaves copies Vich never moved; a successful one has already moved them away, so only the leftovers are deleted
    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function removeTemporaryFiles(): void
    {
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryPaths = [];
    }
}
