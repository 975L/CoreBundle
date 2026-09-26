<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\CacheWarmer;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Twig\Environment;

// Writes public/maintenance.html, the page Apache serves while a deployment rewrites vendor/ and the container, from the very template MaintenanceListener renders once the application answers again: one panel for the whole deployment, and nothing for a site to keep in step
class MaintenancePageCacheWarmer implements CacheWarmerInterface
{
    public const string FILENAME = 'maintenance.html';

    public function __construct(
        private readonly Environment $twig,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $html = $this->twig->render('@c975LConfig/maintenance/index.html.twig', ['standalone' => true]);

        // Temp file + rename(), atomic on the same filesystem, so a 503 served mid-warmup never gets a truncated page
        $path = $this->projectDir . '/public/' . self::FILENAME;
        $tmpPath = $path . '.' . uniqid('', true) . '.tmp';
        if (false === @file_put_contents($tmpPath, $html) || !@rename($tmpPath, $path)) {
            @unlink($tmpPath);

            throw new \RuntimeException(sprintf('Unable to write "%s".', $path));
        }

        return [];
    }
}
