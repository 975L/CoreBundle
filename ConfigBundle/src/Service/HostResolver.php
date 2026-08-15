<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

// Whether a hostname exists at all, kept out of DeploymentHealthCheckProvider so it stays testable without dns. What it is for: a failed http call means two opposite things depending on the answer here - nothing serving that hostname, or something serving it badly
class HostResolver
{
    // Both record types are asked for in one call: a host served over IPv6 only has no A record whatsoever, and gethostbyname() would report it as not existing at all. Errors are silenced rather than raised, an unresolvable name being the very answer wanted here
    public function resolves(string $host): bool
    {
        $records = @dns_get_record($host, \DNS_A | \DNS_AAAA);

        return \is_array($records) && [] !== $records;
    }
}
