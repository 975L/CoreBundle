<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use HeadlessChromium\Browser;
use HeadlessChromium\Browser\BrowserProcess;

// Starts a headless Chrome the way BrowserFactory::createBrowser() does, but registers BrowserProcess::kill() at shutdown only once a browser exists: chrome-php registers it before starting, so a launch timing out between its connection and its browser left a kill() calling sendCloseMessage() on null when PHP exits, turning a caught failure or a retry that worked into an exit code of 255; a failed process is dropped instead, the Symfony Process it holds stopping Chrome when destroyed, and chrome-php/chrome being a require-dev of this bundle, nothing here ever runs on a deployed site
/**
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
final class HeadlessChrome
{
    // A browser on the given binary, killed with the PHP process unless closed before
    /** @param array<string, mixed> $options */
    public static function launch(string $binary, array $options): Browser
    {
        $process = new BrowserProcess();
        $process->start($binary, $options);
        register_shutdown_function($process->kill(...));

        return $process->getBrowser();
    }
}
