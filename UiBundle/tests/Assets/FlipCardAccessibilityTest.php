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

// A card folded in 3D hides its back face from the eye only: everything that makes the fold usable by a
// keyboard, a screen reader, or a browser with no JS at all is spread over three files that no browser here
// runs, so each end is checked against the contract the other two assume
class FlipCardAccessibilityTest extends TestCase
{
    private const CONTROLLER_JS = 'assets/js/flip-card.js';
    private const BARREL = 'assets/controllers.js';
    private const COMPONENT = 'templates/components/FlipCard/FlipCard.html.twig';
    private const STYLESHEET = 'sass/_flip-card.scss';

    // Public pages only, and lazily: the barrel imports it on demand, for a document that actually holds one
    public function testTheControllerIsRegisteredAsALazyFrontControllerNamedAfterWhatTheTemplateWrites(): void
    {
        $barrel = $this->read(self::BARREL);

        $this->assertStringContainsString("flipCard: () => import('./js/flip-card.js'),", $barrel);
        $this->assertStringContainsString('data-controller="flipCard"', $this->read(self::COMPONENT));
    }

    // backface-visibility hides the face turned away from the eye and from nothing else - its buttons stay in
    // the tab order and in the accessibility tree until "inert" takes them out
    public function testTheFaceTurnedAwayIsMadeInert(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('static targets = ["face"];', $controller);
        $this->assertStringContainsString('face.toggleAttribute("inert"', $controller);
        $this->assertStringContainsString('data-flipCard-target="face"', $this->read(self::COMPONENT));
    }

    // Turning the card takes the focused button out of the tab order: without moving focus onto the revealed
    // face, a keyboard user is dropped back to the top of the document and a screen reader says nothing at all
    public function testFocusFollowsTheRevealedFace(): void
    {
        $this->assertStringContainsString('.querySelector(".flip-card-toggle")?.focus();', $this->read(self::CONTROLLER_JS));
    }

    // A real <button>, so it is reachable and operable by keyboard with no key handling of our own. The label
    // says which side comes up, and the aria-label repeats it before naming the card - WCAG's "Label in Name"
    // asks that the accessible name contain the visible one, not replace it
    public function testTheToggleIsARealButtonWhoseAccessibleNameStartsWithItsVisibleLabel(): void
    {
        $component = $this->read(self::COMPONENT);

        $this->assertStringContainsString('<button type="button" class="btn btn-primary flip-card-toggle" data-action="flipCard#toggle"', $component);
        $this->assertStringContainsString('aria-label="{{ \'label.flip_card_show_back\'|trans({}, \'ui\') }}{% if title|default(\'\') %} : {{ title }}{% endif %}"', $component);
        $this->assertStringContainsString('aria-label="{{ \'label.flip_card_show_front\'|trans({}, \'ui\') }}{% if backTitle|default(\'\') %} : {{ backTitle }}{% endif %}"', $component);
    }

    // The fold is an enhancement: the stylesheet stacks the two faces only under the class the controller adds,
    // so a page whose JS never ran shows both faces in normal flow instead of one hidden behind a rotation
    // nothing can undo - and the buttons that would turn nothing stay hidden until the controller unhides them
    public function testTheFoldAndItsButtonsOnlyExistOnceTheControllerRan(): void
    {
        $stylesheet = $this->read(self::STYLESHEET);
        $controller = $this->read(self::CONTROLLER_JS);

        foreach (['backface-visibility: hidden;', 'transform-style: preserve-3d;', 'transform: rotateY(180deg);'] as $rule) {
            foreach ($this->declarationsOutsideTheJsScope($stylesheet) as $selector => $declarations) {
                $this->assertStringNotContainsString($rule, $declarations, sprintf('"%s" applies the fold from "%s", which no ".flip-card--js" guards.', self::STYLESHEET, $selector));
            }
        }

        $this->assertStringContainsString('this.element.classList.add("flip-card--js");', $controller);
        $this->assertStringContainsString('button.removeAttribute("hidden")', $controller);
        $this->assertStringContainsString('data-action="flipCard#toggle"', $this->read(self::COMPONENT));
        $this->assertStringContainsString(' hidden>', $this->read(self::COMPONENT));
    }

    // Both moves are decoration: the faces still swap and the card still says it can be turned for a visitor
    // who asked their system for less motion, they just stop spinning and swaying to get there
    public function testTheTurnAndTheSwayAreBothDroppedUnderReducedMotion(): void
    {
        $stylesheet = $this->read(self::STYLESHEET);

        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $stylesheet);
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: reduce\) \{.*transition: none;.*\}/s', $stylesheet);
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: reduce\) \{.*animation: none;.*\}/s', $stylesheet);
    }

    // The sway is the catalogue's own keyframes, and it runs under the pointer only - never on its own, so
    // nothing here is moving content a visitor would have to be given a way to stop
    public function testTheSwayIsTheSharedKeyframesAndOnlyRunsUnderThePointer(): void
    {
        $this->assertStringContainsString('@keyframes rotateY5deg', $this->read('sass/_animations.scss'));

        foreach ($this->declarationsByRule($this->read(self::STYLESHEET)) as $selector => $declarations) {
            if (!str_contains($declarations, 'animation: rotateY5deg')) {
                continue;
            }

            $this->assertStringContainsString(':hover', $selector, 'The sway must be bound to the pointer, not started on its own.');
            // These keyframes write the whole transform, so on a turned card they would drop the back face
            // back to the front at every cycle - the rotateY(180deg) holding it up is the same property
            $this->assertStringContainsString(':not(.is-flipped)', $selector, 'The sway must stay off the turned card.');

            return;
        }

        self::fail(sprintf('"%s" no longer runs the shared "rotateY5deg" keyframes at all.', self::STYLESHEET));
    }

    // A ratio names its own value and nothing else: what reads it holds the row open from an empty cell-mate,
    // so a face holding more than the shape grows past it instead of being clipped or hidden behind a scrollbar
    public function testARatioIsAFloorUnderTheCardRatherThanACrop(): void
    {
        $stylesheet = $this->read(self::STYLESHEET);

        $this->assertMatchesRegularExpression('/\.flip-card-ratio-3-2 \{\s*--flip-card-ratio: 3 \/ 2;\s*\}/', $stylesheet);
        $this->assertMatchesRegularExpression('/\.flip-card--js \.flip-card-inner::before \{[^}]*aspect-ratio: var\(--flip-card-ratio, auto\);/s', $stylesheet);

        foreach (['overflow: hidden', 'overflow: auto', 'height: 100%'] as $crop) {
            $this->assertStringNotContainsString($crop, $stylesheet, sprintf('"%s" clips a face instead of letting it grow past the ratio.', self::STYLESHEET));
        }
    }

    // Written by the component against the closed list, never interpolated from what a block stored
    public function testTheRatioClassIsMatchedRatherThanBuiltFromStoredData(): void
    {
        $this->assertStringContainsString("{% set ratioClass = ratio|default('free') in ['2-3', '3-4', '9-16', '1-1', '3-2', '4-3', '16-9', '21-9'] ? 'flip-card-ratio-' ~ ratio : '' %}", $this->read(self::COMPONENT));
    }

    // Blocks of both card kinds share one ".cards" row rather than each opening its own
    public function testAFlipCardJoinsTheSameRowAsAPlainCard(): void
    {
        $blocks = $this->read('templates/components/Blocks/Blocks.html.twig');

        $this->assertStringContainsString("{% set cardKinds = ['card', 'flip_card'] %}", $blocks);
        $this->assertStringContainsString('{% set isCard = block.kind in cardKinds %}', $blocks);
    }

    /**
     * Rule bodies keyed by selector. Top-level rules only: anything nested in an at-rule is indented, and the
     * two checks reading this are about what applies unconditionally.
     *
     * @return array<string, string>
     */
    private function declarationsByRule(string $stylesheet): array
    {
        preg_match_all('/^([^@\s][^{]*)\{([^}]*)\}/m', $stylesheet, $matches, PREG_SET_ORDER);

        $rules = [];
        foreach ($matches as $match) {
            $rules[trim($match[1])] = $match[2];
        }

        $this->assertNotEmpty($rules, 'No rule read at all, the test itself is broken.');

        return $rules;
    }

    /**
     * Rule bodies of every selector the ".flip-card--js" class does not gate, keyed by selector.
     *
     * @return array<string, string>
     */
    private function declarationsOutsideTheJsScope(string $stylesheet): array
    {
        return array_filter(
            $this->declarationsByRule($stylesheet),
            static fn (string $selector): bool => !str_contains($selector, 'flip-card--js'),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
