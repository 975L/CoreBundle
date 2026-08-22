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

// A tile whose click turns something off (maintenance, registration, a bundle's test mode - see ShortcutProviderInterface) is warning-colored, so an admin reads what the site currently has switched on without going through every label. The class the template writes and the rule the stylesheets declare are two files apart, and a class nothing paints leaves every tile looking the same.
class ShortcutTileWarningTest extends TestCase
{
    private const array CSS = ['/public/css/management.css', '/public/css/management.min.css'];

    private const string WARNING_CLASS = 'shortcut-tile-warning';

    public function testTheTemplateWearsTheWarningClassOnAnActiveTileOnly(): void
    {
        $template = $this->template();

        $this->assertStringContainsString("shortcut.active|default(false) ? ' " . self::WARNING_CLASS . "' : ''", $template, 'The warning class no longer follows the tile\'s "active" flag.');
    }

    // Every row carries its category heading, the grouping being the whole point of building categories rather than one flat grid
    public function testTheTemplateTitlesEveryCategoryRow(): void
    {
        $template = $this->template();

        $this->assertStringContainsString('<h3 class="shortcuts-category">{{ category.label }}</h3>', $template);
        $this->assertStringContainsString('is_granted(shortcut.role)', $template, 'A row whose tiles are all hidden by role would leave its heading standing above nothing.');
    }

    // The section heading is written here rather than by the caller, the roles deciding whether anything is left to title being read in this file only
    public function testTheTemplateDrawsItsOwnSectionHeadingOnlyWhenARowSurvives(): void
    {
        $template = $this->template();

        $this->assertStringContainsString('{% set visibleCategories = categories|filter(', $template);
        $this->assertStringContainsString('{% if visibleCategories|length > 0 %}', $template);
        $this->assertLessThan(
            mb_strpos($template, "<h2>{{ ('label.shortcuts'|trans({}, 'config')) }}</h2>"),
            mb_strpos($template, '{% if visibleCategories|length > 0 %}'),
            'The section heading stands outside the test hiding it once every row is filtered out.'
        );
    }

    public function testBothStylesheetsPaintTheWarningTile(): void
    {
        foreach (self::CSS as $file) {
            $css = $this->css($file);

            $this->assertStringContainsString('.' . self::WARNING_CLASS . '{border-color:var(--bs-warning-border-subtle);background:var(--bs-warning-bg-subtle);color:var(--bs-warning-text-emphasis)', $css, $file . ' paints no warning tile any more.');
            // The shared hover rule outranks the plain class, so the variant needs a hover of its own, written after it
            $this->assertStringContainsString('.' . self::WARNING_CLASS . ':hover{background:var(--bs-warning-border-subtle)', $css, $file . ' lets the neutral hover repaint a warning tile.');
            $this->assertLessThan(mb_strpos($css, '.' . self::WARNING_CLASS . ':hover'), mb_strpos($css, '.shortcut-tile:hover'), $file . ' declares the neutral hover after the warning one, which wins on equal specificity.');
        }
    }

    private function template(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/management/_shortcuts.html.twig');
    }

    // Same shape whichever of the two stylesheets it comes from - only the space around the punctuation differs
    private function css(string $file): string
    {
        $css = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(\dirname(__DIR__, 2) . $file));

        return (string) preg_replace('#\s*([:;{},/])\s*#', '$1', $css);
    }
}
