<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Fixtures;

use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;

// A provider living in a c975L bundle's namespace, which a PHPUnit stub does not: what GuidedProjectBuilder reads the bundle's name off
class GuidedProjectProviderStub implements GuidedProjectProviderInterface
{
    public function __construct(
        private readonly array $projects,
    ) {
    }

    public function getGuidedProjects(): array
    {
        return $this->projects;
    }
}
