<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;

// The pencil over an entity's public page, and over the cards and fields a cached fragment marks: what the templates must hold for edit-pencils.js and blockEditOverlay to meet, read out of the files that actually ship
class EntityEditPencilTest extends TestCase
{
    // Registered lazily, under the kebab-case identifier the "data-edit-pencils-pattern-value" binding is derived from
    public function testTheControllerIsRegisteredUnderTheIdentifierTheLayoutWrites(): void
    {
        $this->assertStringContainsString("'edit-pencils': () => import('./js/edit-pencils.js')", $this->read('assets/controllers.js'));
        $this->assertStringContainsString('data-controller="blockEditOverlay edit-pencils"', $this->read('templates/layout.html.twig'));
    }

    // Mounted by the layout itself and not in its footer block, which SiteBundle's layout replaces - and only when the pattern exists, which is null for anyone but an editor
    public function testTheLayoutMountsItOutsideTheFooterForEditorsOnly(): void
    {
        $layout = $this->read('templates/layout.html.twig');

        $this->assertMatchesRegularExpression('/\{% set editPattern = entity_edit_pattern\(\) %\}\s*\{% if editPattern %\}\s*<div hidden data-controller="blockEditOverlay edit-pencils"/', $layout);
        $this->assertGreaterThan(strpos($layout, '{% block footer %}{% endblock %}'), strpos($layout, 'entity_edit_pattern()'));
    }

    // The url is written on the wrapper and nowhere else: a fiche rendered inside the component comes out untouched for a visitor
    public function testTheComponentWrapsTheEntityOnlyWhenItHasAnUrl(): void
    {
        $component = $this->read('templates/components/Edit/Entity.html.twig');

        $this->assertStringContainsString('{% set editUrl = entity_edit_url(entity) %}', $component);
        $this->assertMatchesRegularExpression('/\{% if editUrl %\}\s*(\{#.*#\}\s*)?<div class="block-editable" data-block-edit-url="\{\{ editUrl \}\}">\{\{ content \}\}<\/div>\s*\{% else %\}\s*\{\{ content \}\}/s', $component);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/' . $path);
    }
}
