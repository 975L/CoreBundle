<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Entity;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use PHPUnit\Framework\TestCase;

class HealthCheckResultTest extends TestCase
{
    // One value too long for its column fails the single flush a whole run is written in, so the url, label and edit url are cut to fit
    public function testTheVarcharColumnsAreCutToFit(): void
    {
        $long = str_repeat('é', 300);
        $result = new HealthCheckResult()->setUrl($long)->setLabel($long)->setEditUrl($long);

        $this->assertSame(255, mb_strlen($result->getUrl()));
        $this->assertSame(255, mb_strlen((string) $result->getLabel()));
        $this->assertSame(255, mb_strlen((string) $result->getEditUrl()));
    }

    // An absent label or edit url stays absent
    public function testNullStaysNull(): void
    {
        $result = new HealthCheckResult()->setLabel(null)->setEditUrl(null);

        $this->assertNull($result->getLabel());
        $this->assertNull($result->getEditUrl());
    }
}
