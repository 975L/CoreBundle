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

// A freeflow slider whose slides snap on their start shows several at once, left-aligned (SocialBundle's google_reviews block): each turn brings the next slide to the left edge, and once the last ones are all in view the row starts over instead of standing still for a turn per slide left
#[Group('browser')]
class SliderStartAlignedRowTest extends JsCase
{
    private const string STATE = 'const list = root.querySelector(".slider-list");
        const state = () => ({
            scroll: Math.round(list.scrollLeft),
            dot: [...root.querySelectorAll(".slider-dot")].findIndex((d) => d.classList.contains("current")),
        });
        const next = async () => { root.querySelector(".slider-next").click(); await new Promise((r) => setTimeout(r, 400)); };';

    // Each turn moves the row by one slide, the slide asked for landing on the left edge
    public function testEachTurnBringsTheNextSlideToTheLeftEdge(): void
    {
        $state = $this->row('await next(); return state();');

        $this->assertSame(100, $state['scroll'], 'The second slide is not brought to the left edge of the row.');
        $this->assertSame(1, $state['dot']);
    }

    // Five slides, three in view: two turns reach the end, the third starts over rather than standing still
    public function testTheRowStartsOverOnceItsLastSlidesAreInView(): void
    {
        $atEnd = $this->row('await next(); await next(); return state();');
        $over = $this->row('await next(); await next(); await next(); return state();');

        $this->assertSame(200, $atEnd['scroll'], 'The row does not reach its end.');
        $this->assertSame(0, $over['scroll'], 'Past its end the row stands still instead of starting over.');
        $this->assertSame(0, $over['dot'], 'The dots do not follow the row back to its first slide.');
    }

    private function row(string $probe): array
    {
        $slides = '';
        $dots = '';
        for ($index = 0; $index < 5; ++$index) {
            $slides .= sprintf('<li class="slider-item">Slide %d</li>', $index + 1);
            $dots .= sprintf('<button type="button" class="slider-dot" data-slide="%d" aria-label="Slide %d"></button>', $index, $index + 1);
        }

        return (array) $this->observe(
            sprintf(
                '<section class="slider slider-freeflow" id="row-1" data-controller="slider" data-slider-id="row-1">
                    <ul class="slider-list">%s</ul>
                    <button type="button" class="slider-prev">Prev</button>
                    <button type="button" class="slider-next">Next</button>
                    %s
                </section>',
                $slides,
                $dots
            ),
            ['slider' => 'slider'],
            self::STATE . $probe,
            ['css' => '.slider-list { display: flex; width: 300px; overflow-x: auto; scroll-behavior: auto; padding: 0; margin: 0; list-style: none; } .slider-item { flex: 0 0 100px; height: 50px; scroll-snap-align: start; }', 'settle' => 80]
        );
    }
}
