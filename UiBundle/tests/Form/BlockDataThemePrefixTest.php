<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use PHPUnit\Framework\TestCase;

// A kind's data sub-form is themed once, on the "ui_block_data" block prefix, and is reached from two places: the EasyAdmin edit screen through BlockType, and the fragment the kind picker loads over AJAX through BlockFormController. Either one dropping the option is what makes the same kind lay its fields out differently on the two screens - a fieldset grouping and a hoisted media collection on one, a flat run of inputs on the other - with nothing failing and nothing said
class BlockDataThemePrefixTest extends TestCase
{
    private const PREFIX = 'ui_block_data';

    private function source(string $path): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/' . $path);
    }

    public function testBothEntryPointsThemeTheDataSubFormOnTheSharedPrefix(): void
    {
        $this->assertStringContainsString(
            "'block_prefix' => '" . self::PREFIX . "'",
            $this->source('src/Form/BlockType.php'),
            'The EasyAdmin edit screen no longer reaches the shared block theme, so a kind grouping its fields renders them flat there.'
        );
        $this->assertStringContainsString(
            "'block_prefix' => '" . self::PREFIX . "'",
            $this->source('src/Controller/BlockFormController.php'),
            'The fragment the kind picker loads no longer reaches the shared block theme.'
        );
    }

    // The prefix names a block of the theme, and a theme declaring nothing under it would leave both screens on Symfony's own default without either of them saying so
    public function testTheThemeDeclaresThatPrefix(): void
    {
        $this->assertStringContainsString(
            '{% block ' . self::PREFIX . '_widget %}',
            $this->source('templates/form/block_theme.html.twig')
        );
    }

    // The two conventions that block offers a kind, both read off the view rather than hard-coded per kind
    public function testTheThemeReadsTheFieldsetMarkerAndTheMediaHoist(): void
    {
        $theme = $this->source('templates/form/block_theme.html.twig');

        $this->assertStringContainsString("row_attr['data-block-fieldset']", $theme);
        $this->assertStringContainsString('media_after', $theme);
    }

    // flip_card is the kind those two exist for, and the only one using them today - it is what would notice first
    public function testFlipCardAsksForBothOfThem(): void
    {
        $type = $this->source('src/Form/Block/FlipCardType.php');

        $this->assertStringContainsString("'data-block-fieldset' => 'label.face_front'", $type);
        $this->assertStringContainsString("'data-block-fieldset' => 'label.face_back'", $type);
        $this->assertStringContainsString("\$view->vars['media_after']", $type);
    }
}
