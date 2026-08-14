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

// A card folded in 3D hides its back face from the eye only: everything that makes the fold usable by a keyboard, a screen reader, or a browser with no JS at all is spread over three files that no browser here runs, so each end is checked against the contract the other two assume
class FlipCardAccessibilityTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/flip-card.js';
    private const string BARREL = 'assets/controllers.js';
    private const string COMPONENT = 'templates/components/FlipCard/FlipCard.html.twig';
    private const string STYLESHEET = 'sass/_flip-card.scss';

    // Public pages only, and lazily: the barrel imports it on demand, for a document that actually holds one
    public function testTheControllerIsRegisteredAsALazyFrontControllerNamedAfterWhatTheTemplateWrites(): void
    {
        $barrel = $this->read(self::BARREL);

        $this->assertStringContainsString("flipCard: () => import('./js/flip-card.js'),", $barrel);
        $this->assertStringContainsString('data-controller="flipCard"', $this->read(self::COMPONENT));
    }

    // backface-visibility hides the face turned away from the eye and from nothing else - its buttons stay in the tab order and in the accessibility tree until "inert" takes them out
    public function testTheFaceTurnedAwayIsMadeInert(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('static targets = ["face"];', $controller);
        $this->assertStringContainsString('face.toggleAttribute("inert"', $controller);
        $this->assertStringContainsString('data-flipCard-target="face"', $this->read(self::COMPONENT));
    }

    // Turning the card takes the focused button out of the tab order: without moving focus onto the revealed face, a keyboard user is dropped back to the top of the document and a screen reader says nothing at all
    public function testFocusFollowsTheRevealedFace(): void
    {
        $this->assertStringContainsString('.querySelector(".flip-card-toggle")?.focus();', $this->read(self::CONTROLLER_JS));
    }

    // A real <button>, so it is reachable and operable by keyboard with no key handling of our own. It carries no visible label of its own: what a visitor sees and clicks is the face's own title, the button filling that zone - so its accessible name has to say both which side comes up and which card is turning
    public function testTheToggleIsARealButtonNamingBothTheSideItRevealsAndTheCard(): void
    {
        $component = $this->read(self::COMPONENT);

        $this->assertStringContainsString('<button type="button" class="flip-card-toggle', $component);
        $this->assertStringContainsString('aria-label="{{ showBack }}{% if title|default(\'\') %} : {{ title }}{% endif %}"', $component);
        $this->assertStringContainsString('aria-label="{{ showFront }}{% if backTitle|default(\'\') %} : {{ backTitle }}{% endif %}"', $component);
        $this->assertStringContainsString("{% set showBack = 'label.flip_card_show_back'|trans({}, 'ui') %}", $component);
        $this->assertStringContainsString("{% set showFront = 'label.flip_card_show_front'|trans({}, 'ui') %}", $component);
    }

    // The whole face is the control: the toggle fills it, so the gesture is the one these cards have always asked for - click the card, anywhere on it, and it turns
    public function testTheWholeFaceIsWhatTurnsTheCard(): void
    {
        $this->assertStringContainsString('position: relative;', $this->declarationsOf('.flip-card-face'));

        $toggle = $this->declarationsOf('.flip-card-toggle');
        $this->assertStringContainsString('position: absolute;', $toggle);
        $this->assertStringContainsString('inset: 0;', $toggle);
        $this->assertStringContainsString('cursor: pointer;', $toggle);
    }

    // The toggle sits under the content, which lets the pointer through to it - a heading and a rich text are not phrasing content, so a <button> wrapping either would be invalid where layering them is not
    public function testTheContentLetsThePointerThroughExceptWhereItIsItselfAControl(): void
    {
        $this->assertStringContainsString('z-index: 0;', $this->declarationsOf('.flip-card-toggle'));

        $content = $this->declarationsOf(".flip-card-title,\n.flip-card-body");
        $this->assertStringContainsString('pointer-events: none;', $content);
        $this->assertStringContainsString('z-index: 1;', $content);

        // A verso commonly holds the very links the card exists to offer: they must not turn it over instead
        $this->assertStringContainsString('pointer-events: auto;', $this->declarationsOf('.flip-card-body summary'));
    }

    // A hit area nothing paints is invisible to a keyboard without a ring of its own: the face's own title shows no focus, being a heading the button merely covers
    public function testTheInvisibleToggleStillShowsAFocusRing(): void
    {
        $this->assertStringContainsString('outline', $this->declarationsOf('.flip-card-toggle:focus-visible'));
    }

    // The kind is not a card: it shares the ".cards" row and its measure, and nothing else. Reaching back into ".card" is what made a turn reveal a header band where the design asked for a titled face - and what made retuning a card silently move every flip card on the site
    public function testTheFlipCardBorrowsNothingFromTheCard(): void
    {
        // "flip-" excluded first, or every ".flip-card-body" below would read as the card's own ".card-body"
        $component = str_replace('flip-card', 'twoSided', $this->read(self::COMPONENT));

        foreach (['card-header', 'card-body', 'card-data', 'card--accent-'] as $cardClass) {
            $this->assertStringNotContainsString($cardClass, $component, sprintf('"%s" reaches back into ".card" through "%s".', self::COMPONENT, $cardClass));
        }
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*\bcard\b/', $component);

        $adapter = $this->read('templates/blocks/FlipCard.html.twig');
        $this->assertStringContainsString("'flip-card--accent-' ~ accent", $adapter);
        $this->assertStringNotContainsString('card--accent-', str_replace('flip-card--accent-', '', $adapter), 'The block adapter still writes the card\'s own accent classes.');

        // Selectors only, comments stripped: this file says the word ".card" a lot, explaining what it does not do
        foreach (array_keys($this->declarationsByRule($this->stylesheetWithoutComments())) as $selector) {
            // "(?!\w)" is what tells ".card-header" (flagged) from ".cards", the shared row (allowed) and from ".flip-card-body", this kind's own (allowed, the prefix failing the group before it)
            $this->assertDoesNotMatchRegularExpression('/(^|[\s,>])\.card(?!\w)/', trim($selector), sprintf('"%s" styles a ".card" from "%s".', self::STYLESHEET, trim($selector)));
        }
    }

    // The fold is the stylesheet's own, applied on the first paint: keyed on a class the controller adds, every card is painted with its two faces stacked one under the other and collapses into shape once the module loads - a flash of the whole verso on every page load. What still waits for the controller are the toggles, which would turn nothing before it
    public function testTheCardIsPaintedAlreadyFoldedAndOnlyItsButtonsWaitForTheController(): void
    {
        $stylesheet = $this->read(self::STYLESHEET);
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringNotContainsString('flip-card--js', $stylesheet, 'The fold is gated behind a class JS adds, so the two faces are painted unfolded until it lands.');
        $this->assertStringNotContainsString('flip-card--js', $controller);
        $this->assertStringContainsString('backface-visibility: hidden;', $this->declarationsOf('.flip-card-face'));
        $this->assertStringContainsString('transform-style: preserve-3d;', $this->declarationsOf('.flip-card-inner'));

        $this->assertStringContainsString('button.removeAttribute("hidden")', $controller);
        $this->assertStringContainsString('data-action="flipCard#toggle"', $this->read(self::COMPONENT));
        $this->assertStringContainsString(' hidden>', $this->read(self::COMPONENT));
    }

    // The one case the fold has to be undone for: with no JS the toggles stay hidden, and a back face left behind a rotation nothing can undo is content no visitor can reach. "@media (scripting: none)" is what tells the two apart with no class to wait for - a <noscript><style> said the same thing from the component itself, but a style element is metadata content and invalid anywhere but the head
    public function testABrowserRunningNoJsGetsTheFoldUndone(): void
    {
        $stylesheet = $this->read(self::STYLESHEET);

        $this->assertStringNotContainsString('<noscript>', $this->read(self::COMPONENT), 'The fallback is back in the markup, where a <style> element is invalid.');
        $this->assertStringContainsString('@media (scripting: none)', $stylesheet);

        // The block is last in the file on purpose: a media query adds no specificity, so source order alone is what beats the folded rules above it
        $fallback = substr($stylesheet, strpos($stylesheet, '@media (scripting: none)'));
        foreach (['display: block;', 'content: none;', 'backface-visibility: visible;', 'transform: none;'] as $declaration) {
            $this->assertStringContainsString($declaration, $fallback, sprintf('The fallback leaves "%s" undeclared, so that face stays unreachable.', $declaration));
        }
    }

    // Both moves are decoration: the faces still swap and the card still says it can be turned for a visitor who asked their system for less motion, they just stop spinning and swaying to get there
    public function testTheTurnAndTheSwayAreBothDroppedUnderReducedMotion(): void
    {
        $stylesheet = $this->read(self::STYLESHEET);

        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $stylesheet);
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: reduce\) \{.*transition: none;.*\}/s', $stylesheet);
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: reduce\) \{.*animation: none;.*\}/s', $stylesheet);
    }

    // The sway is the catalogue's own keyframes, and it runs under the pointer only - never on its own, so nothing here is moving content a visitor would have to be given a way to stop
    public function testTheSwayIsTheSharedKeyframesAndOnlyRunsUnderThePointer(): void
    {
        $this->assertStringContainsString('@keyframes rotateY5deg', $this->read('sass/_animations.scss'));

        foreach ($this->declarationsByRule($this->read(self::STYLESHEET)) as $selector => $declarations) {
            if (!str_contains($declarations, 'animation: rotateY5deg')) {
                continue;
            }

            $this->assertStringContainsString(':hover', $selector, 'The sway must be bound to the pointer, not started on its own.');
            // These keyframes write the whole transform, so on a turned card they would drop the back face back to the front at every cycle - the rotateY(180deg) holding it up is the same property
            $this->assertStringContainsString(':not(.is-flipped)', $selector, 'The sway must stay off the turned card.');
            // An animation overrides a transition on the same property of the same element, so a sway on ".flip-card-inner" swallows the very turn the click asks for - and the pointer is on the card by definition at the moment it is clicked, so it swallows every one of them. Two elements, two transforms, no contest
            $this->assertStringEndsWith('.flip-card-front', trim($selector), 'The sway must run on the front face, never on the element whose transform the turn transitions.');

            return;
        }

        self::fail(sprintf('"%s" no longer runs the shared "rotateY5deg" keyframes at all.', self::STYLESHEET));
    }

    // Inside "preserve-3d" the inner's own box - and the ratio spacer drawn on it - sit flat at z: 0, while the swaying front face has half of itself turned behind that plane. The half behind loses every hit test to the box in front of it, so the toggle filling the face stops answering over it: that half is the whole left or right of the card, it swaps four times a second under the sway, and the card reads as ignoring a click until a second or third one lands on a phase where the half clicked happens to be the one turned forward
    public function testTheSwayNeverTakesHalfTheCardOutOfReachOfThePointer(): void
    {
        $this->assertStringContainsString('pointer-events: none;', $this->declarationsOf('.flip-card-inner'), 'The inner keeps its own hit area, which swallows the click over whichever half of the swaying face is turned behind it.');
        $this->assertStringContainsString('pointer-events: auto;', $this->declarationsOf('.flip-card-face'), 'Both faces must take the pointer back from the inner, or the toggle filling them answers nothing at all.');
    }

    // The mark saying the card turns is gated on the control that turns it: a toggle is rendered only on a card that has a verso, and stays hidden until the controller has unhidden it - so the mark cannot appear on a card that would not turn, nor on a page whose module never arrived
    public function testTheHintIsGatedOnAToggleThatIsLive(): void
    {
        $hint = $this->declarationsOf('.flip-card-face:has(.flip-card-toggle:not([hidden]))::after');

        $this->assertStringContainsString('content: "";', $hint);
        // Masked, not served: the shape is painted by "background-color" and follows the accent an editor picked, where a fill baked into the data URI would stay one color across the twelve
        $this->assertStringContainsString('mask-image: $hint;', $hint);
        $this->assertStringContainsString('background-color: var(--flip-card-accent, var(--primary));', $hint);
        $this->assertStringNotContainsString('background-image', $hint, 'The mark is painted from the file\'s own fill, so it stops following the accent.');
        $this->assertStringContainsString('--flip-card-hint-size:', $this->read('sass/_tokens.scss'));
    }

    // Summoned by the pointer and by nothing else - a card at rest is left exactly as it was designed. A keyboard never gets a hover, so reaching the toggle brings it up too, that focus being the same moment for a keyboard as the pointer landing is for a mouse
    public function testTheHintIsSummonedByThePointerAndByTheFocusRing(): void
    {
        $this->assertStringContainsString('opacity: 0;', $this->declarationsOf('.flip-card-face:has(.flip-card-toggle:not([hidden]))::after'));

        foreach (['.flip-card:hover .flip-card-face::after', '.flip-card-face:has(.flip-card-toggle:focus-visible)::after'] as $selector) {
            $raised = false;
            foreach ($this->declarationsByRule($this->read(self::STYLESHEET)) as $key => $declarations) {
                if (str_contains($key, $selector) && str_contains($declarations, 'opacity: 1;')) {
                    $raised = true;
                }
            }

            $this->assertTrue($raised, sprintf('"%s" never brings the mark up for "%s".', self::STYLESHEET, $selector));
        }
    }

    // Drawn on the face rather than on the toggle's own ::after, which the body would paint over: the toggle sits under the content and carries a stacking context of its own, so a mark drawn on it could only be given room by holding a gutter open under the content on every card - which is a permanent cost for something the pointer summons
    public function testTheHintIsLiftedOverTheContentRatherThanGivenAGutter(): void
    {
        $hint = $this->declarationsOf('.flip-card-face:has(.flip-card-toggle:not([hidden]))::after');

        $this->assertStringContainsString('z-index: 2;', $hint);
        // Over the content, it would otherwise take the click over the one corner of the face that has to keep turning the card
        $this->assertStringContainsString('pointer-events: none;', $hint);
        $this->assertStringNotContainsString('padding-bottom: calc(var(--flip-card-hint-size)', $this->read(self::STYLESHEET), 'The faces hold a gutter open for a mark that is only ever painted under the pointer.');
    }

    // A ratio names its own value and nothing else: what reads it holds the row open from an empty cell-mate, so a face holding more than the shape grows past it instead of being clipped or hidden behind a scrollbar
    public function testARatioIsAFloorUnderTheCardRatherThanACrop(): void
    {
        $stylesheet = $this->read(self::STYLESHEET);

        $this->assertMatchesRegularExpression('/\.flip-card-ratio-3-2 \{\s*--flip-card-ratio: 3 \/ 2;\s*\}/', $stylesheet);
        $this->assertMatchesRegularExpression('/\.flip-card-inner::before \{[^}]*aspect-ratio: var\(--flip-card-ratio, auto\);/s', $stylesheet);

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

    // Everything one selector declares, every rule carrying it concatenated: a selector is written more than once here on purpose - what paints a face and what folds it are two separate concerns. Looked up by suffix rather than by key, a "//" comment above a rule being swallowed into the selector by declarationsByRule()
    private function declarationsOf(string $selector): string
    {
        $found = [];
        foreach ($this->declarationsByRule($this->read(self::STYLESHEET)) as $key => $declarations) {
            if (str_ends_with(trim($key), $selector)) {
                $found[] = $declarations;
            }
        }

        if ([] === $found) {
            self::fail(sprintf('"%s" declares no "%s" rule at all.', self::STYLESHEET, $selector));
        }

        return implode("\n", $found);
    }

    // Comments carry the word ".card" all over this file, saying what the kind deliberately does not reuse
    private function stylesheetWithoutComments(): string
    {
        return (string) preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', $this->read(self::STYLESHEET));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
