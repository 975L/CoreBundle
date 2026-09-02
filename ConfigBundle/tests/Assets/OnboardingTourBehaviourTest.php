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

// assets/js/onboarding-tour.js walked from end to end, over a sidebar the scenario draws itself
// Almost nothing this controller builds is in the page it is mounted on: the overlay and the panel are appended to <body>, the link it points at belongs to EasyAdmin, and the keys it answers are listened for on the document. A file read as text says none of that - what it leaves behind on the body, and what goes on answering the arrow keys once the tour is closed, are only visible to something that ran it
#[Group('browser')]
class OnboardingTourBehaviourTest extends JsCase
{
    // What OnboardingStepBuilder hands over: the sidebar's own resolved urls, a description only where the menu declared one, and the last of them naming a screen this sidebar no longer shows
    private const array STEPS = [
        ['url' => '/management/config', 'label' => 'La configuration', 'description' => 'Ce que le site sait de lui-meme', 'narration' => 'On ouvre la configuration'],
        ['url' => '/management/page', 'label' => 'Les pages', 'description' => '', 'narration' => ''],
        ['url' => '/management/retire', 'label' => 'Un ecran retire', 'description' => '', 'narration' => ''],
    ];

    private const array LABELS = ['previous' => 'Precedent', 'next' => 'Suivant', 'finish' => 'Terminer', 'close' => 'Fermer'];

    // The tour is only rendered for a sidebar with something in it, and the button offering it must not open on nothing
    public function testATourWithoutASingleStepDrawsNothingAtAll(): void
    {
        $this->assertFalse(
            (bool) $this->tour('begin(); await tick(); return !!panel();', []),
            'A tour with no step opened an empty panel over the back-office.'
        );
    }

    // The panel takes the screen over, so it has to say so: a reader who cannot see the overlay is told by aria-modal alone
    public function testStartingDrawsAModalCarryingItsPositionInTheTour(): void
    {
        $opened = $this->tour(
            'begin();
             await tick();

             return {
                 role: panel().getAttribute("role"),
                 modal: panel().getAttribute("aria-modal"),
                 overlay: !!document.querySelector(".onboarding-tour-overlay"),
                 progress: panel().querySelector(".onboarding-tour-progress").textContent,
                 heading: panel().querySelector("h3").textContent,
                 description: panel().querySelector("p")?.textContent ?? null,
             };'
        );

        $this->assertSame('dialog', $opened['role'], 'The panel is not announced as a dialog, so a screen reader walks straight past it into the page it covers.');
        $this->assertSame('true', $opened['modal'], 'The panel does not say it is modal, and a reader goes on reading the page underneath the overlay.');
        $this->assertTrue($opened['overlay'], 'Nothing was drawn over the page, so the tour points at a back-office that is still fully usable behind it.');
        $this->assertSame('1 / 3', $opened['progress'], 'The panel does not say where in the tour the reader is.');
        $this->assertSame('La configuration', $opened['heading']);
        $this->assertSame('Ce que le site sait de lui-meme', $opened['description'], 'The description the menu declared never reaches the panel.');
    }

    // A menu without a description shows its label alone rather than an empty paragraph
    public function testAStepWithoutADescriptionShowsItsLabelAlone(): void
    {
        $this->assertNull(
            $this->tour('begin(); await tick(); key("ArrowRight"); await tick(); return panel().querySelector("p")?.textContent ?? null;'),
            'A step the menu declared no description for still draws an empty paragraph under its label.'
        );
    }

    // The first step has nothing before it, and a button offering to go there would walk out of the array
    public function testTheFirstStepOffersNoWayBack(): void
    {
        $walked = $this->tour(
            'begin();
             await tick();
             const first = named("Precedent").disabled;
             key("ArrowRight");
             await tick();

             return { first, second: named("Precedent").disabled };'
        );

        $this->assertTrue($walked['first'], 'The first step offers a way back to a step that does not exist.');
        $this->assertFalse($walked['second'], 'The way back is refused on a step that has one before it.');
    }

    // The last step ends the tour rather than walking past the end of the array, and says so before it is clicked
    public function testTheLastStepEndsTheTourRatherThanWalkingPastIt(): void
    {
        $ended = $this->tour(
            'begin();
             await tick();
             key("ArrowRight");
             key("ArrowRight");
             await tick();
             const label = named("Terminer") ? "Terminer" : buttons().join("/");
             named("Terminer").click();
             await tick();

             return { label, panel: !!panel(), overlay: !!document.querySelector(".onboarding-tour-overlay") };'
        );

        $this->assertSame('Terminer', $ended['label'], 'The last step offers to go to a next one, which there is not.');
        $this->assertFalse($ended['panel'], 'The tour is over and its panel is still on screen.');
        $this->assertFalse($ended['overlay'], 'The overlay outlived the tour, leaving the back-office covered by something nothing can close.');
    }

    // The whole tour is walked with the keyboard, which is the only way through it for someone not using a mouse
    public function testTheArrowKeysWalkTheTourBothWays(): void
    {
        $walked = $this->tour(
            'begin();
             await tick();
             key("ArrowRight");
             key("ArrowRight");
             await tick();
             const forward = panel().querySelector(".onboarding-tour-progress").textContent;
             key("ArrowLeft");
             await tick();

             return { forward, back: panel().querySelector(".onboarding-tour-progress").textContent };'
        );

        $this->assertSame('3 / 3', $walked['forward'], 'The right arrow does not walk the tour forward.');
        $this->assertSame('2 / 3', $walked['back'], 'The left arrow does not walk the tour back.');
    }

    // Two ways out that a modal must both offer: the key everyone presses, and clicking beside it
    public function testEscapeAndClickingBesideThePanelBothCloseTheTour(): void
    {
        $escaped = $this->tour('begin(); await tick(); key("Escape"); await tick(); return !!panel();');
        $clicked = $this->tour('begin(); await tick(); document.querySelector(".onboarding-tour-overlay").click(); await tick(); return !!panel();');

        $this->assertFalse((bool) $escaped, 'Escape does not close the tour, so a keyboard reader is left inside it.');
        $this->assertFalse((bool) $clicked, 'Clicking beside the panel does not close the tour.');
    }

    // The step is matched against the sidebar by its own url, the tour never rendering the sidebar itself
    public function testTheStepOutlinesTheSidebarLinkItNamesAndLetsGoOfIt(): void
    {
        $pointed = $this->tour(
            'begin();
             await tick();
             const first = outlined();
             key("ArrowRight");
             await tick();
             const second = outlined();
             key("ArrowRight");
             await tick();

             return { first, second, third: outlined(), standing: !!panel() };'
        );

        $this->assertSame(['/management/config'], $pointed['first'], 'The step does not outline the sidebar link its url names.');
        $this->assertSame(['/management/page'], $pointed['second'], 'The outline stays on the previous link, so two entries are pointed at at once.');
        $this->assertSame([], $pointed['third'], 'A step naming a screen this sidebar no longer shows outlined something else.');
        $this->assertTrue($pointed['standing'], 'A step pointing at nothing took the tour down, where it costs the highlight and nothing more.');
    }

    // The url comes from the sidebar rather than from a hand-written selector, and a query string carrying a quote would otherwise close the attribute selector it is dropped into
    public function testALinkWhoseUrlCarriesAQuoteIsStillFound(): void
    {
        $steps = [['url' => '/management/page?filters[label]="une"', 'label' => 'Les pages filtrees', 'description' => '', 'narration' => '']];

        $this->assertSame(
            ['/management/page?filters[label]="une"'],
            $this->tour('begin(); await tick(); return outlined();', $steps),
            'A sidebar url carrying a quote is not matched, so the step points at nothing at all.'
        );
    }

    // The panel is what the recorder filming the back-office reads, there being nothing else that knows what a step is meant to sound like
    public function testThePanelCarriesWhatTheStepSoundsLikeForTheRecorder(): void
    {
        $read = $this->tour(
            'begin();
             await tick();
             const first = panel().dataset.narration;
             key("ArrowRight");
             await tick();

             return { first, second: panel().dataset.narration };'
        );

        $this->assertSame('On ouvre la configuration', $read['first'], 'The narration never reaches the panel, so a recorder has nothing to read the step out of.');
        $this->assertSame('', $read['second'], 'A step with no narration carries the previous one, which a recorder would read out over the wrong screen.');
    }

    // The keys are answered on the document, so a listener left behind drives a tour nobody can see from anywhere in the back-office
    public function testTheKeyboardIsGivenBackWhenTheTourCloses(): void
    {
        $this->assertFalse(
            (bool) $this->tour('begin(); await tick(); key("Escape"); await tick(); key("ArrowRight"); await tick(); return !!panel();'),
            'The arrow keys still drive a closed tour, so every arrow press in the back-office reopens it.'
        );
    }

    // Turbo replaces the page around a panel appended to the body: nothing would ever take it off again, and the outline would stay on a link the next page redraws
    public function testDisconnectingTakesTheChromeAndTheOutlineOffThePage(): void
    {
        $left = $this->tour(
            'begin();
             await tick();
             const el = root.querySelector("[data-controller]");
             document.createElement("div").appendChild(el);
             await tick();

             return { panel: !!panel(), overlay: !!document.querySelector(".onboarding-tour-overlay"), outlined: outlined() };'
        );

        $this->assertFalse($left['panel'], 'The panel outlived the element it was built from.');
        $this->assertFalse($left['overlay'], 'The overlay outlived the element it was built from, covering the page that replaced it.');
        $this->assertSame([], $left['outlined'], 'The outline stayed on the sidebar link after the tour was taken down.');
    }

    private function tour(string $probe, ?array $steps = null): mixed
    {
        // What every scenario says the same way: the panel lives on the body rather than in the mount, and the keys are answered on the document
        $preamble = 'const tick = () => new Promise((r) => setTimeout(r, 0));
             const panel = () => document.querySelector(".onboarding-tour-panel");
             const buttons = () => [...panel().querySelectorAll("button")].map((b) => b.textContent);
             const named = (label) => [...panel().querySelectorAll("button")].find((b) => b.textContent === label) ?? null;
             const key = (name) => document.dispatchEvent(new KeyboardEvent("keydown", { key: name, bubbles: true }));
             const outlined = () => [...document.querySelectorAll(".guided-highlight")].map((a) => a.getAttribute("href"));
             const begin = () => root.querySelector("[data-action]").click(); ';

        return $this->observe($this->page($steps ?? self::STEPS), ['onboarding-tour' => 'onboarding-tour'], $preamble . $probe);
    }

    // The mount as _onboarding_tour.html.twig renders it, over a sidebar standing in for EasyAdmin's own
    private function page(array $steps): string
    {
        return sprintf(
            '<div data-controller="onboarding-tour" data-onboarding-tour-steps-value="%s" data-onboarding-tour-labels-value="%s">
                <button type="button" data-action="click->onboarding-tour#start">Visite guidee</button>
            </div>
            <nav>
                <a href="/management/config">Configuration</a>
                <a href="/management/page">Pages</a>
                <a href="/management/page?filters[label]=&quot;une&quot;">Pages filtrees</a>
            </nav>',
            htmlspecialchars(json_encode($steps, \JSON_THROW_ON_ERROR), \ENT_QUOTES),
            htmlspecialchars(json_encode(self::LABELS, \JSON_THROW_ON_ERROR), \ENT_QUOTES)
        );
    }
}
