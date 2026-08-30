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

// The place of every button of a row's toolbar is carried by a class, EasyAdmin's layout nonce'ing style-src and no nonce ever covering a style written from JS. Which moves a failure that used to be impossible - "addToolbarButton" wrote whatever order it was handed - into the stylesheet: an order with no class declared for it leaves the button at the default 0, in front of the move handle, without a single error to show for it.
class BlockToolbarOrderTest extends TestCase
{
    private const array CSS = ['/public/css/management.css', '/public/css/management.min.css'];

    // The middle of the bar, before the delete button: where a button whose order has no class of its own belongs, rather than in front of the handle
    public function testAButtonWithNoOrderClassLandsInTheMiddle(): void
    {
        foreach (self::CSS as $file) {
            $this->assertMatchesRegularExpression(
                '/\.ui-toolbar-btn\{[^{}]*(?:^|;)order:2;?[^{}]*\}/',
                $this->css($file),
                $file . ' no longer gives .ui-toolbar-btn a fallback order, so a button carrying an unknown one jumps to the head of the toolbar.'
            );
        }
    }

    // Same specificity as the fallback above, so only their being declared after it puts them in charge
    public function testTheThreeOrdersAreDeclaredAfterTheFallback(): void
    {
        foreach (self::CSS as $file) {
            $css = $this->css($file);
            $fallback = strpos($css, '.ui-toolbar-btn{');

            foreach ([1, 2, 3] as $order) {
                // The minified sheet drops the last semicolon of a rule, hence the optional one
                $rule = sprintf('/\.ui-toolbar-btn--order-%d\{order:%d;?\}/', $order, $order);

                $this->assertMatchesRegularExpression($rule, $css, sprintf('%s no longer declares the order-%d class, so a button asking for that order gets the fallback.', $file, $order));

                preg_match($rule, $css, $matches, \PREG_OFFSET_CAPTURE);
                $this->assertGreaterThan($fallback, $matches[0][1], sprintf('%s declares the order-%d class before .ui-toolbar-btn, whose own order then wins on the cascade.', $file, $order));
            }
        }
    }

    // EasyAdmin's own delete button carries no ".ui-toolbar-btn" class of its own, so its place is written on it directly - and it has to stay behind the hide toggle that took the 3 it used to hold
    public function testTheDeleteButtonIsOrderedLast(): void
    {
        foreach (self::CSS as $file) {
            $this->assertMatchesRegularExpression(
                '/\.ui-row-toolbar \.field-collection-delete-button\{(?:[^{}]*;)?order:4[;}]/',
                $this->css($file),
                $file . ' no longer places the delete button after the hide toggle, so the two swap places in the toolbar.'
            );
        }
    }

    // What ties the two together: the class name the toolbar composes has to be one of those three
    public function testTheToolbarPlacesItsButtonsByClass(): void
    {
        $js = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/js/block-toolbar.js');

        $this->assertStringContainsString('ui-toolbar-btn--order-${order}', $js, 'The toolbar no longer places its buttons by class, and a style written from JS is not covered by the nonce.');
        $this->assertDoesNotMatchRegularExpression('/\.style\.order/', $js, "The order is written as an inline style again, which a nonce'd style-src drops.");
    }

    // Same shape whichever of the two stylesheets it comes from - only the space around the punctuation differs
    private function css(string $file): string
    {
        $path = \dirname(__DIR__, 2) . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('/\s+/', ' ', (string) file_get_contents($path));

        return (string) preg_replace('#\s*([:;{},/])\s*#', '$1', $css);
    }
}
