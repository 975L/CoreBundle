<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Management\AbstractDeclaredFilesHealthCheckProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

// The smallest subclass the abstract accepts, so its own behaviour is checked without a bundle's repositories in the way. Its own file (not inlined in the test class) - src/Tests classes are autoloadable by consuming apps, whose attribute route loader recursively reflects every class under the bundle root, and PSR-4 requires one file per class for that to work.
class DeclaredFilesHealthCheckProviderTestSubject extends AbstractDeclaredFilesHealthCheckProvider
{
    /**
     * @param array<int, array{filename: string, label: string, editUrl: ?string, directory?: string}> $files
     */
    public function __construct(
        private readonly array $files,
        ConfigServiceInterface $configService,
        TranslatorInterface $translator,
        string $projectDir,
    ) {
        parent::__construct($configService, $translator, $projectDir);
    }

    public function getKind(): string
    {
        return 'files-test';
    }

    protected function declaredFiles(): iterable
    {
        yield from $this->files;
    }
}
