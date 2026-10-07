<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;

// A site-wide row's label leads to its edit screen, its url being a mere key, while a page row's label still opens the tested url
class HealthCheckLabelLinkTest extends TestCase
{
    public function testASiteWideRowLabelLeadsToItsEditScreen(): void
    {
        $template = $this->template();

        $this->assertStringContainsString('{% if not (group ?? true) and result.editUrl %}', $template);
        $this->assertStringContainsString('<a href="{{ result.editUrl }}">{{ result.label ?: result.url }}</a>', $template);
    }

    public function testAPageRowLabelStillOpensTheTestedUrl(): void
    {
        $this->assertStringContainsString('<a href="{{ result.url }}" target="_blank" rel="noopener">{{ result.label ?: result.url }}</a>', $this->template());
    }

    private function template(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/management/health_check/_table.html.twig');
    }
}
