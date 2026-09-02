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

// assets/js/guided-project.js walked with a project the scenario answers for and a storage it reads back
// This is the one controller here whose whole subject is what it remembers: the panel is rebuilt from browser storage on every admin page, and what the dashboard buttons say is read from the same place. None of that can be seen in the file - a project resumed one step short, a paused one coming back unasked, or a finished one still offering to be resumed are all differences of stored state, not of code
// The mount sits on every admin page (see GuidedProjectMountBuilder), so what it leaves listening on the document matters as much as what it draws
#[Group('browser')]
class GuidedProjectBehaviourTest extends JsCase
{
    private const array LABELS = [
        'previous' => 'Precedent',
        'next' => 'Suivant',
        'finish' => 'Terminer',
        'goto' => 'Y aller',
        'pause' => 'Pause',
        'quit' => 'Quitter',
        'start' => 'Commencer',
        'resume' => 'Reprendre',
        'replay' => 'Refaire',
    ];

    private const string KEY = 'c975l.guided-project.tester';

    // A page carrying the mount and nothing stored is the normal case on every admin screen, and it must cost no request at all
    public function testNothingStoredOpensNoPanelAndAsksTheServerNothing(): void
    {
        $quiet = $this->project('return { panel: !!panel(), requests: window.__requests.length };');

        $this->assertFalse($quiet['panel'], 'A panel opened on a browser that has no project running.');
        $this->assertSame(0, $quiet['requests'], 'The mount asked the server for a project nobody started, on every admin page.');
    }

    public function testStartingAProjectDrawsItsFirstStepAndRemembersWhereItIs(): void
    {
        $started = $this->project(
            'slug("pages").click();
             await tick();

             return {
                 progress: panel().querySelector(".guided-project-progress").textContent,
                 heading: panel().querySelector("h3").textContent,
                 active: stored().active,
                 asked: window.__requests,
             };'
        );

        $this->assertSame('Les pages — 1 / 3', $started['progress'], 'The panel does not say which project is running, nor where in it the user is.');
        $this->assertSame('Ouvrir la liste', $started['heading']);
        $this->assertSame(['slug' => 'pages', 'step' => 0], $started['active'], 'The position was not stored, so the next page load loses the project.');
        $this->assertSame(['/management/guided-project/pages'], $started['asked'], 'The slug did not reach the route the mount was given.');
    }

    // The server renders every button the same way, this browser filling in what it alone knows
    public function testTheDashboardButtonsReadWhatThisBrowserRemembers(): void
    {
        $read = $this->project(
            'return {
                 running: slug("pages").textContent,
                 done: slug("menu").textContent,
                 badges: [...root.querySelectorAll("[data-guided-project-badge]")].map((b) => b.dataset.guidedProjectBadge + ":" + b.hidden),
             };',
            'window.localStorage.setItem("c975l.guided-project.tester", JSON.stringify({ done: ["menu"], active: { slug: "pages", step: 1, paused: true } }));'
        );

        $this->assertSame('Reprendre', $read['running'], 'A project left open offers to be started over rather than resumed.');
        $this->assertSame('Refaire', $read['done'], 'A finished project offers to be started rather than replayed.');
        $this->assertSame(['pages:true', 'menu:false'], $read['badges'], 'The done badge is shown beside the wrong project.');
    }

    // The flag is the whole difference between the panel coming back on every page and coming back when asked for
    public function testAPausedProjectStaysClosedUntilItsButtonIsClicked(): void
    {
        $resumed = $this->project(
            'const closed = !!panel();
             slug("pages").click();
             await tick();

             return { closed, progress: panel().querySelector(".guided-project-progress").textContent, active: stored().active };',
            'window.localStorage.setItem("c975l.guided-project.tester", JSON.stringify({ active: { slug: "pages", step: 1, paused: true } }));'
        );

        $this->assertFalse($resumed['closed'], 'A paused project reopened its panel by itself on the next admin page.');
        $this->assertSame('Les pages — 2 / 3', $resumed['progress'], 'The project did not come back on the step it was left on.');
        $this->assertSame(['slug' => 'pages', 'step' => 1], $resumed['active'], 'The paused flag survived the project being asked for again, so the panel closes itself on the next page.');
    }

    // A slug is stored by one browser and the project it names is declared by a provider that can be taken away, or gated by a role the user has since lost
    public function testAProjectNoProviderDeclaresAnyMoreIsForgotten(): void
    {
        $dropped = $this->project(
            'return { panel: !!panel(), active: stored().active ?? null, button: slug("pages").textContent };',
            'window.__found = false;
             window.localStorage.setItem("c975l.guided-project.tester", JSON.stringify({ active: { slug: "pages", step: 1 } }));'
        );

        $this->assertFalse($dropped['panel'], 'A project the server no longer serves still opened a panel, over steps nothing could fill.');
        $this->assertNull($dropped['active'], 'The unknown slug stayed stored, so every admin page asks the server for it again.');
        $this->assertSame('Commencer', $dropped['button'], 'The dashboard still offers to resume a project that no longer exists.');
    }

    // A step's url comes from the back-office, so following it blind would turn an edited project into a one-click redirect
    public function testAStepLeadingOffSiteIsRenderedInPlaceRatherThanFollowed(): void
    {
        foreach (['https://ailleurs.example/creer', 'javascript:alert(1)'] as $url) {
            $refused = $this->project(
                'slug("pages").click();
                 await tick();
                 named("Suivant").click();
                 await tick();
                 const offered = named("Y aller") ? "Y aller" : buttons().join("/");
                 named("Y aller").click();
                 await tick();

                 return { offered, progress: panel().querySelector(".guided-project-progress").textContent, where: window.location.pathname };',
                sprintf('window.__project.steps[1].url = %s;', json_encode($url, \JSON_THROW_ON_ERROR))
            );

            $this->assertSame('Y aller', $refused['offered'], 'A step carrying an url does not say that it leads somewhere.');
            $this->assertSame('Les pages — 3 / 3', $refused['progress'], sprintf('The step carrying "%s" was not rendered in place, so the project stops on it.', $url));
            $this->assertSame('/', $refused['where'], sprintf('The panel followed "%s", which is a redirect anyone who can edit a project could write.', $url));
        }
    }

    public function testTheLastStepFinishesTheProjectAndMarksItDone(): void
    {
        $finished = $this->project(
            'slug("pages").click();
             await tick();
             named("Suivant").click();
             await tick();
             named("Suivant").click();
             await tick();
             named("Terminer").click();
             await tick();

             return { panel: !!panel(), state: stored(), button: slug("pages").textContent, badge: badge("pages").hidden };'
        );

        $this->assertFalse($finished['panel'], 'The project is over and its panel is still beside the page.');
        $this->assertSame(['done' => ['pages']], $finished['state'], 'A finished project did not go into the done list, or left its position stored beside it.');
        $this->assertSame('Refaire', $finished['button'], 'A finished project is not offered to be replayed.');
        $this->assertFalse($finished['badge'], 'The done badge stays hidden on a project that has just been finished.');
    }

    // The two ways out differ only in whether the stored position survives, which is exactly what a reader cannot check in the file
    public function testPausingKeepsWhereTheProjectGotToAndQuittingDropsIt(): void
    {
        $paused = $this->project($this->walked('named("Pause").click();'));
        $quit = $this->project($this->walked('named("Quitter").click();'));

        $this->assertFalse($paused['panel'], 'Pausing left the panel on screen.');
        $this->assertSame(['slug' => 'pages', 'step' => 1, 'paused' => true], $paused['state']['active'], 'Pausing lost where the project got to, so it starts over.');
        $this->assertSame('Reprendre', $paused['button'], 'A paused project is not offered to be resumed.');

        $this->assertFalse($quit['panel'], 'Quitting left the panel on screen.');
        $this->assertArrayNotHasKey('active', $quit['state'], 'Quitting kept the position, so the project comes back on the next admin page.');
        $this->assertSame('Commencer', $quit['button'], 'A project quit halfway is not offered to be started again.');
        $this->assertArrayNotHasKey('done', $quit['state'], 'A project quit halfway was recorded as done.');
    }

    // Storage is refused by a browser in private mode or by a policy, and it must cost the resume and the badge rather than the project
    public function testABrowserRefusingStorageStillWalksTheProject(): void
    {
        $walked = $this->project(
            'slug("pages").click();
             await tick();
             named("Suivant").click();
             await tick();

             return panel().querySelector(".guided-project-progress").textContent;',
            'Object.defineProperty(window, "localStorage", { configurable: true, get() { throw new Error("refused"); } });'
        );

        $this->assertSame('Les pages — 2 / 3', $walked, 'A browser refusing storage takes the whole project down rather than only what could not be remembered.');
    }

    // The buttons are listened for on the document, the list being rendered on the dashboard while the mount is appended elsewhere. A listener left behind opens a project from a click anywhere on any later page
    public function testTheDocumentListenerDoesNotOutliveTheController(): void
    {
        $gone = $this->project(
            'document.createElement("div").appendChild(root.querySelector("[data-controller]"));
             await tick();
             slug("pages").click();
             await tick();

             return { panel: !!panel(), requests: window.__requests.length };'
        );

        $this->assertFalse($gone['panel'], 'A click opened a project through a controller that is no longer on the page.');
        $this->assertSame(0, $gone['requests'], 'The document is still listened to after the mount was taken away, so every click on the site can reach the server.');
    }

    public function testTheListIsOpenedAndClosedByItsOwnButton(): void
    {
        $toggled = $this->project(
            'const list = root.querySelector("[data-guided-project-list]");
             root.querySelector("[data-guided-project-toggle]").click();
             const opened = list.hidden;
             root.querySelector("[data-guided-project-toggle]").click();

             return { opened, closed: list.hidden };'
        );

        $this->assertFalse($toggled['opened'], 'The button offering the projects does not open the list.');
        $this->assertTrue($toggled['closed'], 'The list cannot be closed again.');
    }

    // The panel sits beside the work rather than over it, so the form the step asks the user to fill stays reachable
    public function testThePanelNeverTakesTheFocusAwayFromTheScreenItComments(): void
    {
        $panel = $this->project(
            'slug("pages").click();
             await tick();

             return { role: panel().getAttribute("role"), modal: panel().getAttribute("aria-modal"), overlay: !!document.querySelector(".onboarding-tour-overlay") };'
        );

        $this->assertSame('complementary', $panel['role'], 'The panel announces itself as a dialog, which traps the focus the user needs in the form underneath it.');
        $this->assertNull($panel['modal'], 'The panel says it is modal over a screen the step asks the user to work in.');
        $this->assertFalse($panel['overlay'], 'A project covered the page it is walking through.');
    }

    // The recorder filming the back-office reads the panel itself, a step that deliberately points at nothing being told apart from one whose target has moved
    public function testThePanelCarriesWhatTheStepSoundsLikeAndWhatItPointsAt(): void
    {
        $read = $this->project(
            'slug("pages").click();
             await tick();
             const first = { ...panel().dataset };
             named("Suivant").click();
             await tick();

             return { first, second: { ...panel().dataset } };'
        );

        $this->assertSame(['narration' => 'On ouvre la liste', 'highlight' => '#cible'], $read['first'], 'The panel carries neither what the step sounds like nor what it points at.');
        $this->assertSame(['narration' => '', 'highlight' => ''], $read['second'], 'A step with neither narration nor target carries the previous one, which a recorder reads out over the wrong screen.');
    }

    public function testTheStepOutlinesWhatItNamesAndLetsGoOfItAfterwards(): void
    {
        $pointed = $this->project(
            'slug("pages").click();
             await tick();
             const first = outlined();
             named("Suivant").click();
             await tick();
             const second = outlined();
             named("Suivant").click();
             await tick();
             named("Terminer").click();
             await tick();

             return { first, second, after: outlined() };'
        );

        $this->assertSame(['cible'], $pointed['first'], 'The step does not outline the element it names.');
        $this->assertSame([], $pointed['second'], 'The outline stayed on the previous step, so two things are pointed at at once.');
        $this->assertSame([], $pointed['after'], 'The outline outlived the project, marking an element on every page that follows.');
    }

    // Walked to the second step and then left, the two exits being told apart by what survives
    private function walked(string $exit): string
    {
        return 'slug("pages").click();
             await tick();
             named("Suivant").click();
             await tick();
             ' . $exit . '
             await tick();

             return { panel: !!panel(), state: stored(), button: slug("pages").textContent };';
    }

    private function project(string $probe, string $seed = ''): mixed
    {
        $preamble = 'const KEY = ' . json_encode(self::KEY, \JSON_THROW_ON_ERROR) . ';
             const tick = () => new Promise((r) => setTimeout(r, 0));
             const panel = () => document.querySelector(".guided-project-panel");
             const buttons = () => [...panel().querySelectorAll("button")].map((b) => b.textContent);
             const named = (label) => [...panel().querySelectorAll("button")].find((b) => b.textContent === label) ?? null;
             const slug = (name) => root.querySelector("[data-guided-project-slug=" + name + "]");
             const badge = (name) => root.querySelector("[data-guided-project-badge=" + name + "]");
             const outlined = () => [...document.querySelectorAll(".guided-highlight")].map((el) => el.id);
             const stored = () => JSON.parse(window.localStorage.getItem(KEY) ?? "{}"); ';

        return $this->observe(
            $this->page(),
            ['guided-project' => 'guided-project'],
            $preamble . $probe,
            // The project route answered by the scenario, which is also what keeps this test off the network. The seed goes last: it stands for what this browser already remembers, and one scenario refuses the storage it would be written to
            ['before' => $this->answers() . $seed]
        );
    }

    private function answers(): string
    {
        $steps = [
            ['label' => 'Ouvrir la liste', 'description' => 'On commence par la liste', 'highlight' => '#cible', 'narration' => 'On ouvre la liste'],
            ['label' => 'Creer une page'],
            ['label' => 'Publier'],
        ];

        return sprintf(
            'window.__requests = [];
             window.__found = true;
             window.__project = { slug: "pages", label: "Les pages", steps: %s };
             window.fetch = (url) => {
                 window.__requests.push(url);

                 return Promise.resolve(window.__found
                     ? { ok: true, json: () => Promise.resolve(window.__project) }
                     : { ok: false, json: () => Promise.reject(new Error("not found")) });
             };',
            json_encode($steps, \JSON_THROW_ON_ERROR)
        );
    }

    // The mount GuidedProjectMountBuilder appends to every admin page, the dashboard list rendered by _guided_projects.html.twig, and something for a step to point at
    private function page(): string
    {
        return sprintf(
            '<div data-controller="guided-project" data-guided-project-key-value="tester" data-guided-project-url-value="/management/guided-project/__SLUG__" data-guided-project-labels-value="%s"></div>
            <button type="button" data-guided-project-toggle>Parcours guides</button>
            <div data-guided-project-list hidden>
                <ul>
                    <li><button type="button" data-guided-project-slug="pages">Commencer</button><span data-guided-project-badge="pages" hidden>Fait</span></li>
                    <li><button type="button" data-guided-project-slug="menu">Commencer</button><span data-guided-project-badge="menu" hidden>Fait</span></li>
                </ul>
            </div>
            <a id="cible" href="/management/page">La liste</a>',
            htmlspecialchars(json_encode(self::LABELS, \JSON_THROW_ON_ERROR), \ENT_QUOTES)
        );
    }
}
