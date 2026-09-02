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

// assets/js/slider.js clicked, swiped and left to play on its own - the largest script this bundle ships, and the one holding the most state between one gesture and the next
// The index is tracked rather than read off the class, because the outgoing slide keeps its own for another 500 ms: a second gesture landing mid-transition is precisely the case that used to leave two slides showing and the dots pointing at neither, and it is only reachable by actually driving the thing
#[Group('browser')]
class SliderBehaviourTest extends JsCase
{
    // The current slide, the dots and what the live region last announced
    // Read off aria-hidden rather than off "slider-item-active": the outgoing slide keeps that class for the 500 ms its transition lasts, so during a change two slides carry it and the first one found is the one being left
    private const string STATE = 'const state = () => ({
        active: [...root.querySelectorAll(".slider-item")].findIndex((s) => !s.hasAttribute("aria-hidden")),
        dot: [...root.querySelectorAll(".slider-dot")].findIndex((d) => d.classList.contains("current")),
        said: root.querySelector(".slider-liveregion")?.textContent ?? "",
        hidden: [...root.querySelectorAll(".slider-item")].map((s) => s.getAttribute("aria-hidden")),
    });';

    // The first slide is the one shown, and the only one not hidden from a screen reader
    public function testTheSliderOpensOnItsFirstSlideWithTheOthersHiddenFromScreenReaders(): void
    {
        $state = $this->slider('return state();');

        $this->assertSame(0, $state['active'], 'The slider opens on no slide at all.');
        $this->assertSame(0, $state['dot'], 'No dot points at the slide being shown.');
        $this->assertSame([null, 'true', 'true'], $state['hidden'], 'The slides not on screen are not hidden from a screen reader, which reads all three one after the other.');
    }

    // Past the last slide it comes back to the first, and before the first it goes to the last
    public function testTheSliderWrapsAroundAtBothEnds(): void
    {
        $forward = $this->slider('next(); next(); next(); return state();');
        $backward = $this->slider('prev(); return state();');

        $this->assertSame(0, $forward['active'], 'Going past the last slide does not come back to the first.');
        $this->assertSame(2, $backward['active'], 'Going back from the first slide does not reach the last.');
        $this->assertSame(2, $backward['dot'], 'The dots do not follow the slide when it wraps around.');
    }

    // Every change is announced, which is all a screen reader gets: the slides themselves are hidden
    public function testEachChangeIsAnnouncedButTheOpeningSlideIsNot(): void
    {
        $this->assertSame('', $this->slider('return state();')['said'], 'The slider announces a change on opening, over a slide the reader has not been moved from.');
        $this->assertSame('Item 2 of 3', $this->slider('next(); return state();')['said'], 'A change of slide is not announced, so a screen reader is told nothing at all.');
    }

    // A dot is a jump to its own slide, whichever way that is
    public function testADotJumpsStraightToItsOwnSlide(): void
    {
        $state = $this->slider('root.querySelectorAll(".slider-dot")[2].click(); await tick(); return state();');

        $this->assertSame(2, $state['active'], 'A dot does not take the slider to its own slide.');
        $this->assertSame('Item 3 of 3', $state['said']);
    }

    // A click on the slide advances it - unless it lands on a link, which is the slide's own address and not a request for the next one
    public function testClickingASlideAdvancesItUnlessTheClickWasOnALink(): void
    {
        $this->assertSame(1, $this->slider('root.querySelector(".slider-item").click(); await tick(); return state();')['active'], 'Clicking a slide no longer advances the slider.');
        $this->assertSame(0, $this->slider('root.querySelector(".slider-item a").click(); await tick(); return state();')['active'], 'A click on a slide\'s own link advanced the slider, so the link is followed and the slide changes under it.');
    }

    // A swipe ends in a click the browser fires anyway, and acting on it would move the slider twice for one gesture
    public function testTheClickThatEndsASwipeDoesNotAdvanceTheSliderASecondTime(): void
    {
        $this->assertSame(
            1,
            $this->slider(
                'const slide = root.querySelector(".slider-item");
                 const box = slide.getBoundingClientRect();
                 const at = (type, x) => slide.dispatchEvent(new TouchEvent(type, { bubbles: true, changedTouches: [new Touch({ identifier: 1, target: slide, clientX: x, clientY: box.y + 10 })] }));
                 at("touchstart", box.x + 200);
                 at("touchend", box.x + 20);
                 await tick();
                 slide.click();
                 await tick();

                 return state();'
            )['active'],
            'The click the browser fires at the end of a swipe advanced the slider a second time, so one gesture moved it twice.'
        );
    }

    // Left alone it plays; hovered it stops, because a slide changing under a reader is the whole reason the pause exists
    public function testItPlaysOnItsOwnAndStopsUnderThePointer(): void
    {
        $played = $this->slider('await new Promise((r) => setTimeout(r, 260)); return state();', 120);
        $hovered = $this->slider(
            'root.querySelector(".slider").dispatchEvent(new MouseEvent("mouseenter"));
             await new Promise((r) => setTimeout(r, 260));

             return state();',
            120
        );

        $this->assertGreaterThan(0, $played['active'], 'The slider never advances on its own, so a declared duration does nothing.');
        $this->assertSame(0, $hovered['active'], 'The slider goes on playing under the pointer, changing the slide being read.');
    }

    // The button is the reader's own switch, and it has to say which way it now points
    public function testThePlayPauseButtonStopsTheSliderAndSaysSo(): void
    {
        $stopped = $this->slider(
            'const button = root.querySelector(".slider-play-pause");
             button.click();
             await new Promise((r) => setTimeout(r, 260));

             return { ...state(), action: button.getAttribute("data-action"), label: button.getAttribute("aria-label") };',
            120
        );

        $this->assertSame(0, $stopped['active'], 'The slider went on playing after being stopped.');
        $this->assertSame('start', $stopped['action'], 'The button does not turn into a start button once it has stopped the slider.');
        $this->assertStringContainsString('Start', (string) $stopped['label'], 'The button goes on announcing itself as a stop button after stopping the slider.');
    }

    // Turbo caches the page as it stands: an interval left running would go on changing a slider nobody is looking at any more
    public function testThePlayingIntervalDoesNotOutliveTheSlider(): void
    {
        $this->assertSame(
            0,
            $this->slider(
                'const slider = root.querySelector("[data-controller]");
                 // Kept in hand once detached, or the reading below would look for it where it no longer is
                 const slides = [...slider.querySelectorAll(".slider-item")];
                 document.createElement("div").appendChild(slider);
                 await new Promise((r) => setTimeout(r, 400));

                 return { active: slides.findIndex((s) => !s.hasAttribute("aria-hidden")) };',
                120
            )['active'],
            'The autoplay interval outlived the slider, so a page left in the cache goes on advancing.'
        );
    }

    private function slider(string $probe, ?int $duration = null): array
    {
        return (array) $this->observe(
            $this->markup($duration),
            ['slider' => 'slider'],
            self::STATE
            . 'const tick = () => new Promise((r) => setTimeout(r, 40));
               const next = async () => { root.querySelector(".slider-next").click(); await tick(); };
               const prev = async () => { root.querySelector(".slider-prev").click(); await tick(); };
               '
            . $probe,
            ['css' => '.slider-item { width: 300px; height: 120px; }', 'settle' => 80]
        );
    }

    // The markup templates/components/Slider renders, reduced to what the controller drives
    private function markup(?int $duration): string
    {
        $slides = '';
        for ($index = 0; $index < 3; ++$index) {
            $slides .= sprintf('<div class="slider-item"><a href="#s%d">Slide %d</a></div>', $index, $index + 1);
        }

        $dots = '';
        for ($index = 0; $index < 3; ++$index) {
            $dots .= sprintf('<button type="button" class="slider-dot" data-slide="%d" aria-label="Slide %d"></button>', $index, $index + 1);
        }

        return sprintf(
            '<div class="slider" id="slider-1" data-controller="slider" data-slider-id="slider-1"%s>
                <div class="slider-list">%s</div>
                <button type="button" class="slider-prev">Prev</button>
                <button type="button" class="slider-next">Next</button>
                <button type="button" class="slider-play-pause" data-action="stop" aria-label="Stop the slider"></button>
                %s
            </div>',
            null !== $duration ? sprintf(' data-slider-duration="%d"', $duration) : '',
            $slides,
            $dots
        );
    }
}
