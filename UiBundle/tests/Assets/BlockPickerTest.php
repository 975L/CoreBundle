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

// The visual palette an editor picks a block kind from. None of it can be proven from a unit test - it is a browser sheet opened over a form - so what is locked here are the properties that make it safe to put in front of the <select>: the form still posts a kind without it, nothing it adds can submit the page, and it is laid out for the phone first.
class BlockPickerTest extends TestCase
{
    private const string MODULE_JS = 'assets/js/block-picker.js';
    private const string COLLECTION_JS = 'assets/js/block-collection.js';
    private const string ADMIN_BARREL = 'assets/controllers-admin.js';
    private const string STYLESHEET = 'sass/management/_block-picker.scss';
    private const string SILHOUETTES = 'sass/_block-thumbs.scss';
    private const string TRANSLATIONS_JS = 'assets/js/translations.js';
    private const string THUMB_COMPONENT = 'templates/components/Blocks/Thumb.html.twig';

    // Imported for its side effect only, like icon-picker.js: a missing import is the whole feature missing
    public function testTheModuleIsImportedByTheAdminBarrel(): void
    {
        $this->assertStringContainsString("import './js/block-picker.js';", $this->read(self::ADMIN_BARREL));
    }

    // The whole point of standing in front of the select rather than replacing it: every kind-change rule of BlockType reads a posted "kind", and a browser that never ran the module still has the native control
    public function testTheSelectIsNeverRemovedFromTheForm(): void
    {
        $module = $this->read(self::MODULE_JS);

        // That the row ends up carrying the class is BlockPickerBehaviourTest's; the absence of the other way out is what no scenario can show
        $this->assertStringNotContainsString('select.remove()', $module);
        $this->assertStringContainsString('.ui-block-picker-on', $this->read(self::STYLESHEET), 'The select is hidden by the row class, so the stylesheet is what has to carry it.');
    }

    // A site turning EasyAdmin's autocomplete on for this field gets TomSelect, which moves the select inside a wrapper of its own - hiding the select alone would leave that wrapper on screen next to the trigger
    public function testTomSelectsOwnWrapperIsHiddenWithTheSelect(): void
    {
        $this->assertStringContainsString('.ts-wrapper', $this->read(self::STYLESHEET));
    }

    // A <button> with no type is a submit button, and this one sits inside the EasyAdmin form: the first tap would post the page instead of opening the sheet
    public function testEveryButtonItBuildsIsExplicitlyTyped(): void
    {
        $module = $this->read(self::MODULE_JS);

        $this->assertSame(
            substr_count($module, "document.createElement('button')"),
            substr_count($module, ".type = 'button'"),
            'A button is built without its type being set, and would submit the block form.'
        );
    }

    // One event, dispatched here whether or not a site turned TomSelect on for this field: block.js fetches the kind's sub-form on it, and a second one from TomSelect would fetch it twice
    public function testChoosingAKindFiresExactlyOneChange(): void
    {
        $module = $this->read(self::MODULE_JS);

        // EasyAdmin wraps a list of ten choices or more with TomSelect, which no fixture here carries: that it is told to stay silent is the half only the source can answer, the single change reaching block.js being BlockPickerBehaviourTest's
        $this->assertStringContainsString('select.tomselect.setValue(kind, true)', $module, 'TomSelect is no longer told to stay silent, so it fires a change of its own on top of the one below.');
    }

    // The option's own text is "Label (description)", the only way a bare <select> can say what a kind does - printed as is, the palette would show the description twice, once inside the label and once under it
    public function testTheLabelAndTheDescriptionAreReadApart(): void
    {
        $module = $this->read(self::MODULE_JS);

        $this->assertStringContainsString('option.dataset.label', $module);
        $this->assertStringContainsString('option.dataset.description', $module);
    }

    // Rows are cloned into the page by EasyAdmin's collection script, and a container's slots arrive with the sub-form the picker itself just loaded
    public function testRowsArrivingAfterLoadAreEnhancedToo(): void
    {
        $module = $this->read(self::MODULE_JS);

        // The two events are BlockPickerBehaviourTest's; the guard against a row being enhanced twice is what stays here
        $this->assertStringContainsString('if (row.dataset.uiBlockPicker) return;', $module, 'Nothing stops a row from being given a second trigger.');
    }

    // A new block row used to open its kind selector straight away; with the palette in front of it, focusing a hidden select does nothing at all
    public function testANewRowOpensThePaletteRatherThanTheHiddenSelect(): void
    {
        $this->assertStringContainsString('.ui-block-picker-trigger', $this->read(self::COLLECTION_JS));
    }

    // A native dialog does not close on its backdrop, and on a phone the sheet covers the screen: without this there is nowhere obvious to tap to get out
    public function testTheSheetClosesOnItsBackdrop(): void
    {
        $this->assertStringContainsString('if (event.target === element) element.close();', $this->read(self::MODULE_JS));
    }

    // Inside a <form method="dialog">, pressing Enter to validate a search submits that form and closes the sheet on the editor
    public function testTheSearchFieldIsNotInsideAForm(): void
    {
        $this->assertStringNotContainsString("createElement('form')", $this->read(self::MODULE_JS));
    }

    // Anything under 16px makes iOS Safari zoom the sheet in on focus, leaving the editor scrolled sideways in a grid they can no longer see
    public function testTheSearchFieldEscapesTheIosZoom(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.ui-block-picker__search\s*\{[^}]*font-size:\s*16px/s',
            $this->read(self::STYLESHEET)
        );
    }

    // Written for the phone first: the bare rules are the sheet, and the desktop dialog is what a media query adds - not the other way round
    public function testTheSheetIsLaidOutMobileFirst(): void
    {
        $stylesheet = $this->read(self::STYLESHEET);

        $this->assertMatchesRegularExpression(
            '/\.ui-block-picker__grid\s*\{[^}]*grid-template-columns:\s*repeat\(2, 1fr\)/s',
            $stylesheet,
            'The bare grid is no longer the two columns a phone fits.'
        );
        $this->assertStringNotContainsString('@media (max-width', $stylesheet, 'A max-width query would make the desktop layout the base one and the phone the exception.');
        $this->assertStringContainsString('@media (min-width: 36em)', $stylesheet);
    }

    // A kind shipped by another bundle, or one added here without a rule of its own, still has to read as something rather than as an empty frame
    public function testAKindWithNoRuleOfItsOwnStillGetsASilhouette(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.ui-block-thumb__media,\s*\.ui-block-thumb__action\s*\{\s*display:\s*none/s',
            $this->read(self::SILHOUETTES),
            'The default silhouette - a title and two lines - is gone, so an unruled kind shows every part at once.'
        );
    }

    // The silhouettes only earn their place by telling kinds apart: a grid, a hero and a plain text section reading the same would leave the label doing all the work
    public function testTheKindsThatLayOutDifferentlyHaveTheirOwnSilhouette(): void
    {
        $stylesheet = $this->read(self::SILHOUETTES);

        foreach (['hero', 'card', 'image', 'portfolio_grid', 'image_compare', 'map', 'cta_band', 'form', 'flex_columns'] as $kind) {
            $this->assertStringContainsString('.ui-block-thumb--' . $kind, $stylesheet, sprintf('The "%s" kind lost the silhouette that told it apart.', $kind));
        }
    }

    // The picker builds the parts in the browser and the component writes them in Twig, for a page listing kinds outside the back-office - drifting apart, one of the two would draw silhouettes the stylesheet no longer arranges
    public function testTheComponentWritesTheSamePartsAsThePicker(): void
    {
        $module = $this->read(self::MODULE_JS);
        $template = $this->read(self::THUMB_COMPONENT);

        $this->assertSame(1, preg_match("/const THUMB_PARTS = \[([^\]]+)\]/", $module, $declared), 'The list of parts is no longer declared in one place in the picker.');

        preg_match_all("/'(\w+)'/", $declared[1], $parts);
        $this->assertNotEmpty($parts[1]);

        foreach (array_count_values($parts[1]) as $part => $times) {
            $this->assertSame(
                $times,
                substr_count($template, 'ui-block-thumb__' . $part . '"'),
                sprintf('The component does not write the "%s" part as many times as the picker does.', $part)
            );
        }
    }

    // The palette's wording is written in the browser, where a Twig |trans never reaches - a locale missing a key renders that key raw on screen
    public function testItsWordingIsTranslatedInEveryShippedLocale(): void
    {
        $translations = $this->read(self::TRANSLATIONS_JS);

        foreach (['block.picker.search', 'block.picker.close', 'block.picker.empty'] as $key) {
            $this->assertSame(3, substr_count($translations, '"' . $key . '"'), sprintf('The key "%s" is missing from one of the three shipped locales.', $key));
        }
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
