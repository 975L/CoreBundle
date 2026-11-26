<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// A caller may hand the flip card faces of its own, through the "front" and "back" blocks, where a title and a rich text are not what it shows: the default faces stay inside those blocks, so every card written before them renders as it did, and "flip" says there is a back when no back prop does
class FlipCardFacesTest extends TestCase
{
    private const string COMPONENT = 'templates/components/FlipCard/FlipCard.html.twig';

    // Each face's title and body are the default of its block, the toggle staying outside it so a face of one's own still turns the card
    public function testEachFaceIsABlockWrappingItsDefaultContentButNotItsToggle(): void
    {
        $component = (string) file_get_contents(\dirname(__DIR__, 2) . '/' . self::COMPONENT);

        foreach (['front' => '{% if title|default(\'\') %}', 'back' => '{% if backTitle|default(\'\') %}'] as $face => $default) {
            $start = strpos($component, '{% block ' . $face . ' %}');
            $this->assertNotFalse($start, sprintf('No "%s" block.', $face));
            $end = strpos($component, '{% endblock %}', $start);
            $this->assertNotFalse($end);
            $inside = substr($component, $start, $end - $start);
            $this->assertStringContainsString($default, $inside);
            $this->assertStringContainsString('<div class="flip-card-body">', $inside);
            $this->assertStringNotContainsString('flip-card-toggle', $inside, sprintf('The "%s" block swallows the toggle, so a face of one\'s own would turn nothing.', $face));
        }
    }

    // A back given as a block alone is announced by "flip", or the card would render as the plain card it otherwise is
    public function testFlipDeclaresABackNoPropDoes(): void
    {
        $component = (string) file_get_contents(\dirname(__DIR__, 2) . '/' . self::COMPONENT);

        $this->assertStringContainsString("{% set hasBack = flip|default(false)|to_bool or backTitle|default('') or backContent|default('') or backMedia %}", $component);
        $this->assertStringContainsString('[flip="false"]', $component);
    }
}
