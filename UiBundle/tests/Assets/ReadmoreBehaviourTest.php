<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Testing\JsCase;
use PHPUnit\Framework\Attributes\Group;

// readmore.js answers one question no selector can ask: did the fold actually hide anything? The stylesheet clamps the text, and the controller compares the clamped box against its content to decide whether the "read more" link has anything to reveal
// The whole test is a measure, which is why it needs a browser: an emulated DOM answers 0 to both scrollHeight and clientHeight, so the comparison reads 0 <= 1 and every text on earth comes back "complete" - green, and wrong for exactly the case the controller exists to catch
#[Group('browser')]
class ReadmoreBehaviourTest extends JsCase
{
    // The clamp this bundle's sass applies, reduced to what the measure depends on
    private const string CSS = '
        .readmore__content { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; width: 200px; font: 16px/20px sans-serif; }
        .readmore input:checked ~ .readmore__content { -webkit-line-clamp: none; overflow: visible; }
    ';

    public function testATextShorterThanTheFoldIsMarkedCompleteSoNoLinkIsOffered(): void
    {
        $this->assertTrue($this->measure('court'), 'A text the fold does not cut is not reported complete, so a "read more" link is offered over nothing to read.');
    }

    public function testATextTheFoldCutsIsNotMarkedComplete(): void
    {
        $this->assertFalse($this->measure(str_repeat('un texte qui deborde largement ', 20)), 'A text the fold cuts is reported complete, which takes the "read more" link away from a text that is still hidden.');
    }

    // A text sitting exactly on the last folded line rounds a fraction of a pixel apart between the box and its content, and the controller allows one pixel for it
    public function testATextEndingOnTheLastFoldedLineIsStillComplete(): void
    {
        $this->assertTrue($this->measure('deux lignes pile ici'), 'A text ending on the last folded line is not reported complete, so the fractional line-height rounding was let through.');
    }

    // Unfolding resizes the content, which brings the observer back: measuring then would read the whole text as fitting and drop the link the visitor just used
    public function testAnUnfoldedTextIsNeverMeasured(): void
    {
        $long = str_repeat('un texte qui deborde largement ', 20);

        $this->assertFalse($this->measure($long, true), 'The controller measured an unfolded text and called it complete, which removes the link that folds it back.');
    }

    // One measure is never enough: the box is resized by the page around it, and a text that fitted at one width is cut at another
    public function testTheTextIsMeasuredAgainWhenItsBoxIsResized(): void
    {
        $narrowed = $this->observe(
            '<div class="readmore" data-controller="readmore"><input type="checkbox" data-readmore-target="toggle"><p class="readmore__content" data-readmore-target="content">un texte de longueur moyenne</p></div>',
            ['readmore' => 'readmore'],
            'const box = root.firstElementChild;
             const before = box.classList.contains("readmore--complete");
             box.querySelector(".readmore__content").style.width = "40px";
             await new Promise((r) => setTimeout(r, 150));

             return { before, after: box.classList.contains("readmore--complete") };',
            ['css' => self::CSS, 'settle' => 120]
        );

        $this->assertTrue($narrowed['before'], 'The text did not fit to begin with, so the resize being tested proves nothing.');
        $this->assertFalse($narrowed['after'], 'A box narrowed under the text was never measured again, so the "read more" link stays away from a text now cut.');
    }

    // Turbo caches the page as it stands, so a snapshot restored later would carry a class describing a measure taken against another viewport
    public function testDisconnectingLeavesNoClassBehindForATurboSnapshotToRestore(): void
    {
        $left = $this->observe(
            sprintf('<div class="readmore" data-controller="readmore"><input type="checkbox" data-readmore-target="toggle"><p class="readmore__content" data-readmore-target="content">%s</p></div>', 'court'),
            ['readmore' => 'readmore'],
            'const el = root.firstElementChild;
             root.querySelector("[data-controller]").removeAttribute("data-controller");
             await new Promise((r) => requestAnimationFrame(r));

             return el.classList.contains("readmore--complete");',
            ['css' => self::CSS, 'settle' => 120]
        );

        $this->assertFalse($left, 'The controller left "readmore--complete" on the element after disconnecting, so a restored snapshot carries a stale measure.');
    }

    private function measure(string $text, bool $unfolded = false): bool
    {
        return (bool) $this->observe(
            sprintf(
                '<div class="readmore" data-controller="readmore"><input type="checkbox" data-readmore-target="toggle"%s><p class="readmore__content" data-readmore-target="content">%s</p></div>',
                $unfolded ? ' checked' : '',
                htmlspecialchars($text, \ENT_QUOTES)
            ),
            ['readmore' => 'readmore'],
            'return root.firstElementChild.classList.contains("readmore--complete");',
            // A ResizeObserver fires after the frame it observes on, and document.fonts.ready lands later still
            ['css' => self::CSS, 'settle' => 120]
        );
    }
}
