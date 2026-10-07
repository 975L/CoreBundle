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

// assets/js/animate-scroll.js, on the wrappers BlockExtension::wrapInAnimation() writes: a block in view plays its effect, one further down waits hidden
#[Group('browser')]
class AnimateScrollBehaviourTest extends JsCase
{
    public function testABlockInViewPlaysItsEffectAndIsNeverHidden(): void
    {
        $state = $this->animate('<div class="block-animation scroll" id="top" data-animation="fade-in"><p>Top</p></div>');

        $this->assertSame(['animated' => true, 'hidden' => false], $state['top'], 'A block in view did not play its entrance effect.');
    }

    public function testABlockFurtherDownWaitsHiddenWithoutItsEffect(): void
    {
        $state = $this->animate('<div style="height: 5000px"></div><div class="block-animation scroll" id="low" data-animation="fade-in"><p>Low</p></div>');

        $this->assertSame(['animated' => false, 'hidden' => true], $state['low'], 'A block out of view was revealed before being scrolled to.');
    }

    // A kind opening with its own <style> has no box there, so the element observed is the first one drawn
    public function testAKindOpeningWithAStyleIsObservedOnItsFirstDrawnElement(): void
    {
        $state = $this->animate('<div class="block-animation scroll" id="top" data-animation="fade-in"><style>p { color: red; }</style><p>Top</p></div>');

        $this->assertSame(['animated' => true, 'hidden' => false], $state['top'], 'A block opening with a <style> never played its effect.');
    }

    private function animate(string $blocks): mixed
    {
        return $this->observe(
            sprintf('<div class="blocks" data-controller="animateScroll">%s</div>', $blocks),
            ['animateScroll' => 'animate-scroll'],
            'return Object.fromEntries([...document.querySelectorAll(".scroll")].map((el) => [el.id, { animated: el.classList.contains("fade-in"), hidden: el.classList.contains("hidden") }]));',
            ['settle' => 120]
        );
    }
}
