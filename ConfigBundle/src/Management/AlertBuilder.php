<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Entity\Config;
use Symfony\Bundle\SecurityBundle\Security;

// Merges the dashboard alerts contributed by every AlertProvider (bundles depending on ConfigBundle), grouped by severity (danger first)
class AlertBuilder
{
    public function __construct(
        private readonly iterable $alertProviders,
        private readonly Security $security,
    ) {
    }

    // Every alert, across every provider, grouped by severity - one declaring a role the current user lacks is dropped, same treatment as a shortcut tile or a guided project (see GuidedProjectBuilder)
    public function getAlerts(): array
    {
        $alerts = ProviderMerger::merge($this->alertProviders, fn (AlertProviderInterface $provider) => $provider->getAlerts());

        return self::groupBySeverity($this->keepReadable($alerts));
    }

    // The same, for a screen wanting only its own provider's alerts: the role filter belongs here too, an alert naming an entry its reader may not open being a link to a 403 (see ConfigCrudController, and the restricted entries it hides below ROLE_SUPER_ADMIN)
    public function groupOwnBySeverity(array $alerts): array
    {
        return self::groupBySeverity($this->keepReadable($alerts));
    }

    /**
     * @param list<array{severity: string, role?: string}> $alerts
     *
     * @return list<array{severity: string, role?: string}>
     */
    private function keepReadable(array $alerts): array
    {
        return array_values(array_filter(
            $alerts,
            fn (array $alert) => !isset($alert['role']) || $this->security->isGranted($alert['role']),
        ));
    }

    // Groups a flat alert list by severity; the role filter is the caller's above, both of them running it first - an alert naming an entry its reader may not open being a link to a 403
    private static function groupBySeverity(array $alerts): array
    {
        $grouped = [
            Config::SEVERITY_DANGER => [],
            Config::SEVERITY_WARNING => [],
            Config::SEVERITY_INFO => [],
        ];

        foreach ($alerts as $alert) {
            $grouped[$alert['severity']][] = $alert;
        }

        return $grouped;
    }
}
