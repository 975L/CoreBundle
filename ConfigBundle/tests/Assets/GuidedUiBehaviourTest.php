<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Assets;

use PHPUnit\Framework\Attributes\Group;

// assets/js/guided-ui.js, the DOM helpers and the highlighting the onboarding tour and the guided projects share
// What it points at is written by whoever declares a step, so the selector reaching it is data rather than code: a step naming something that has moved, or naming it wrongly, must cost the highlight and nothing else - and the one shape that would take a page down, a selector the browser refuses to parse, is the one nothing in a file can be read for
#[Group('browser')]
class GuidedUiBehaviourTest extends JsCase
{
    // A step aiming at something that is no longer there walks past it rather than stopping the tour
    public function testAStepPointingAtNothingCostsTheHighlightAndNothingElse(): void
    {
        $this->assertNull($this->guided('return mod.ui.highlight("#nowhere");'), 'A step naming an element the page no longer holds does not simply answer nothing.');
    }

    // A selector the browser refuses to parse is written by hand in a step's declaration, and would otherwise throw out of querySelector
    public function testASelectorTheBrowserRefusesToParseIsAnsweredWithNothing(): void
    {
        $this->assertNull($this->guided('return mod.ui.highlight("::::");'), 'A malformed selector throws instead of costing the step its highlight, which stops the tour on the step before it.');
    }

    // The element is outlined and brought into view, and the mark comes off again
    public function testTheTargetIsOutlinedBroughtIntoViewAndCleanedUpAfterwards(): void
    {
        $state = $this->guided(
            'const found = mod.ui.highlight("#target");
             const marked = found?.classList.contains("guided-highlight") ?? false;
             mod.ui.clearHighlight(found);

             return { marked, after: root.querySelector("#target").classList.contains("guided-highlight"), same: found === root.querySelector("#target") };'
        );

        $this->assertTrue($state['marked'], 'The element a step points at is not outlined.');
        $this->assertTrue($state['same'], 'The element found is not the one the selector names.');
        $this->assertFalse($state['after'], 'The outline stays on the element after the step has moved on.');
    }

    // Clicking EasyAdmin's own toggle rather than setting the class, so its accordion and its aria stay in step
    public function testAStepInsideAClosedSubmenuOpensItThroughEasyAdminsOwnToggle(): void
    {
        $opened = $this->guided(
            'let clicks = 0;
             root.querySelector(".ea-sidebar-item-link").addEventListener("click", () => { clicks += 1; });
             mod.ui.highlight("#nested");

             return { clicks, forced: root.querySelector(".has-submenu").classList.contains("is-expanded") };'
        );

        $this->assertSame(1, $opened['clicks'], 'A step inside a closed submenu does not ask EasyAdmin to open it, so the tour points at something nobody can see.');
        $this->assertFalse($opened['forced'], 'The open state was set behind EasyAdmin\'s back, which leaves its accordion and its aria saying the opposite.');
    }

    // A submenu already open is not toggled, which would close it on the step meant to point inside it
    public function testASubmenuAlreadyOpenIsLeftAlone(): void
    {
        $this->assertSame(
            0,
            $this->guided(
                'root.querySelector(".has-submenu").classList.add("is-expanded");
                 let clicks = 0;
                 root.querySelector(".ea-sidebar-item-link").addEventListener("click", () => { clicks += 1; });
                 mod.ui.highlight("#nested");

                 return clicks;'
            ),
            'A submenu already open was toggled, which closes it on the very step meant to point inside it.'
        );
    }

    // The wording of a step is what an admin declared, so it reaches the page as text and never as markup
    public function testWhatAStepDeclaresReachesThePageAsText(): void
    {
        $built = $this->guided(
            'const element = mod.ui.buildElement("p", "step", "<b>gras</b>");
             let fired = 0;
             const button = mod.ui.buildButton("Suivant", () => { fired += 1; });
             button.click();

             return { text: element.textContent, html: element.innerHTML, className: element.className, type: button.type, label: button.textContent, fired };'
        );

        $this->assertSame('<b>gras</b>', $built['text'], 'A step\'s wording does not reach the element as it was written.');
        $this->assertStringNotContainsString('<b>', $built['html'], 'A step\'s wording is interpreted as markup on its way into the page.');
        $this->assertSame('step', $built['className']);
        $this->assertSame('button', $built['type'], 'The button is a submit one, so a step rendered inside a form posts it on the first click.');
        $this->assertSame(1, $built['fired'], 'The button does not do what it was built to do.');
    }

    private function guided(string $probe): mixed
    {
        return $this->observe(
            '<div>
                <div id="target">Cible</div>
                <div class="ea-sidebar-item has-submenu">
                    <a class="ea-sidebar-item-link" href="#">Section</a>
                    <ul><li id="nested">Entree</li></ul>
                </div>
            </div>',
            [],
            $probe,
            ['modules' => ['ui' => 'guided-ui']]
        );
    }
}
