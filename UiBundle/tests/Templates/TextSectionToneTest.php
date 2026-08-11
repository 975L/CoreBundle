<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Form\Block\TextSectionType;
use PHPUnit\Framework\TestCase;

// The "secondary" presentation of a "text_section" - a section standing beside a louder one rather than carrying the page on its own. It spans a form, a block adapter, a component and the stylesheet, and only holds together if the four agree on the one value that writes a class
class TextSectionToneTest extends TestCase
{
    private function read(string $path): string
    {
        $full = \dirname(__DIR__, 2) . '/' . $path;
        $this->assertFileExists($full);

        return (string) file_get_contents($full);
    }

    // Comments document the mechanism and name the classes, which is not markup
    private function template(string $path): string
    {
        return (string) preg_replace('/\{#.*?#\}/s', '', $this->read('templates/' . $path));
    }

    // The value comes from stored block data, so it may only build a class it was matched against first
    public function testTheComponentMatchesTheStoredValueBeforeBuildingTheClass(): void
    {
        $twig = $this->template('components/Text/Section.html.twig');

        $this->assertStringContainsString("tone|default('') == 'secondary'", $twig);
        $this->assertSame(
            1,
            substr_count($twig, 'text-section--'),
            'The component writes the text-section-- prefix more than once, only the matched value may build it.'
        );
    }

    // A component supporting the option is useless if the block adapter never passes the stored value on
    public function testTheBlockAdapterPassesTheStoredToneToItsComponent(): void
    {
        $this->assertStringContainsString('tone="{{ tone|default(\'\') }}"', $this->template('blocks/TextSection.html.twig'));
    }

    // "normal" writes no class on purpose - it is the block's own default, and what every section stored before the field existed renders as. The form offering a third tone would silently render as "normal"
    public function testTheFormOffersExactlyTheOneToneTheStylesheetStyles(): void
    {
        $this->assertSame(['normal', 'secondary'], TextSectionType::TONES);
        $this->assertStringContainsString('.text-section--secondary {', $this->read('sass/_page-sections.scss'));
    }

    // Every value overridable and none read without a fallback, same rule as .text-hook's own scale
    public function testEveryPropertyOfTheVariantIsReadThroughAnOverridableToken(): void
    {
        $scss = $this->read('sass/_page-sections.scss');

        foreach (['--text-section-secondary-size', '--text-section-secondary-line-height', '--text-section-secondary-color'] as $token) {
            $this->assertMatchesRegularExpression(
                '/var\(' . preg_quote($token, '/') . ', [^)]/',
                $scss,
                sprintf('"%s" must be read with a fallback, so the stylesheet holds up without a theme setting it.', $token)
            );
        }
    }
}
