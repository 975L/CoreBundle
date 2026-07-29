<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Service\CssVariableResolver;
use c975L\UiBundle\Twig\CssVariableExtension;
use PHPUnit\Framework\TestCase;

class CssVariableExtensionTest extends TestCase
{
    public function testGetFiltersRegistersTheResolveFilterAsHtmlSafe(): void
    {
        $extension = new CssVariableExtension(new CssVariableResolver());
        $filters = $extension->getFilters();

        $this->assertCount(1, $filters);
        $this->assertSame('resolve_css_variables', $filters[0]->getName());

        // Without it Twig escapes the stylesheet it is handed, and the <style> holds &gt; instead of >
        $this->assertSame(['html'], $filters[0]->getSafe(new \Twig\Node\TextNode('', 0)));
    }

    public function testResolveHandsTheStylesheetToTheResolver(): void
    {
        $extension = new CssVariableExtension(new CssVariableResolver());
        $css = ':root{--brand:#c0392b}.button{background:var(--brand)}';

        $this->assertStringContainsString('background:#c0392b', $extension->resolve($css));
    }

    public function testResolveLeavesAStylesheetWithoutAnyVariableUntouched(): void
    {
        $extension = new CssVariableExtension(new CssVariableResolver());
        $css = '.button{background:#c0392b}';

        $this->assertSame($css, $extension->resolve($css));
    }
}
