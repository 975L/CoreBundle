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

// assets/js/calculator.js and the page's address: an estimate is shared by its link, which has to carry the choices it was made with and set them back when opened
#[Group('browser')]
class CalculatorUrlBehaviourTest extends JsCase
{
    private const string HTML = '<div data-controller="ui-calculator" data-ui-calculator-url-value="/compute">
        <select name="form_submission[type]"><option value="990" selected>Vitrine</option><option value="3000">Sur mesure</option></select>
        <input type="checkbox" name="form_submission[logo]" value="1">
        <input type="text" name="form_submission[email]" value="">
        <div data-ui-calculator-target="results"></div>
    </div>';

    // Only what differs from the defaults is written, and a control put back to its default leaves the address again
    public function testAChoiceIsWrittenIntoTheAddressAndLeavesItOnceUndone(): void
    {
        $written = $this->calculator('const box = root.querySelector("input[type=checkbox]");
            box.checked = true;
            box.dispatchEvent(new Event("change", { bubbles: true }));
            const ticked = window.location.search;
            box.checked = false;
            box.dispatchEvent(new Event("change", { bubbles: true }));
            const unticked = window.location.search;
            window.history.replaceState(null, "", window.location.pathname);

            return { ticked, unticked };');

        $this->assertSame('?logo=1', $written['ticked'], 'A ticked option is not in the address, so sharing the page loses it.');
        $this->assertSame('', $written['unticked'], 'An option put back to its default stays in the address.');
    }

    // A shared link opens on the estimate it was made with, a name or an email never riding along
    public function testASharedLinkSetsTheChoicesBack(): void
    {
        $restored = $this->calculator('const state = { type: root.querySelector("select").value, logo: root.querySelector("input[type=checkbox]").checked };
            window.history.replaceState(null, "", window.location.pathname);

            return state;', '?type=3000&logo=1');

        $this->assertSame(['type' => '3000', 'logo' => true], $restored, 'A shared link opens on the defaults, losing the estimate it was made with.');
    }

    // A value no option offers, typed or left over from an older form, is left alone rather than blanking the choice
    public function testAValueNoOptionOffersIsIgnored(): void
    {
        $type = $this->calculator('const value = root.querySelector("select").value;
            window.history.replaceState(null, "", window.location.pathname);

            return value;', '?type=12345');

        $this->assertSame('990', $type, 'An unknown value from the address blanked the choice.');
    }

    private function calculator(string $probe, string $query = ''): mixed
    {
        return $this->observe(
            self::HTML,
            ['ui-calculator' => 'calculator'],
            $probe,
            [
                // The address a shared link opens on, set before the controller connects; the requests it then sends are answered with nothing
                'before' => sprintf('window.history.replaceState(null, "", window.location.pathname + %s);
                    window.fetch = () => Promise.resolve(new Response("{}", { status: 200 }));', json_encode($query)),
                'settle' => 30,
            ]
        );
    }
}
