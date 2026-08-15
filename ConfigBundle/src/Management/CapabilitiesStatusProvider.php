<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Service\EnvironmentProbe;

// Carries what the site's own process is allowed to do, so a console holding several sites can see which of them lost a capability without opening a control panel per site. What made this worth reporting: a host can withdraw a function from one site and not the next, nothing in the application changes, and the feature relying on it stops silently - the site keeps working, so no error is ever raised and no one finds out until the missing output is noticed by eye.
//
// Keyed "capabilities" rather than "environment": the report already carries an "environment" of its own at its root, which is the Symfony one (prod/dev). Two keys spelt the same at two levels, meaning two unrelated things, is a confusion a receiver pays for every time it reads either.
//
// Reports capabilities, never the host: a value here says what this process can do, and stays just as readable whether the site runs on a shared plan, a managed server or a laptop. Which host is behind it is the maintainer's business, and naming one would date this the day a site moves.
class CapabilitiesStatusProvider implements StatusProviderInterface
{
    public function __construct(
        private readonly EnvironmentProbe $environmentProbe,
    ) {
    }

    public function getStatusKey(): string
    {
        return 'capabilities';
    }

    /**
     * The generic floor only - a SAPI, whether an external command can be run at all, and the limits an upload
     * or a long task hit. What a given bundle needs on top (a binary, an extension) belongs to that bundle's
     * own provider: ConfigBundle has no way to know what is installed alongside it, and a list maintained here
     * would go stale the first time a bundle stops needing something.
     *
     * Read through the web SAPI when the report is fetched over http (see StatusController), which is the one
     * that matters for anything a visitor triggers - and the reason "sapi" travels with the rest rather than
     * being assumed: php-cli is configured separately, so a reading is only ever true of the SAPI it came from.
     *
     * @return array<string, mixed>
     */
    public function getStatusData(): array
    {
        return $this->environmentProbe->describe();
    }
}
