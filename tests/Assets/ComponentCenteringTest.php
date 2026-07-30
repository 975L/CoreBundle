<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Testing\ComponentCenteringAnalyzer;
use c975L\UiBundle\Testing\StylesheetCascade;
use PHPUnit\Framework\TestCase;

// A component laying itself out on the inline axis - centered by "margin: … auto", or broken out of the measure by a negative margin - hugs the left edge as soon as a stronger rule writes the margin shorthand, with nothing in its own sass changed to show for it: that is how the slider broke in v1.12.0, and the hero with a background and the colored flats in v1.13.0. This bundle's sheet alone; SiteBundle runs the same engine over the pair, catching what one sheet on its own cannot show
class ComponentCenteringTest extends TestCase
{
    public function testNoRuleClobbersAComponentsOwnInlineLayout(): void
    {
        $root = dirname(__DIR__, 2);
        $analyzer = new ComponentCenteringAnalyzer(StylesheetCascade::fromFiles($root . '/public/css/styles.css'));

        $result = $analyzer->analyse(ComponentCenteringAnalyzer::tagsByClass($root . '/templates/components'));

        foreach ($result['violations'] as $violation) {
            self::fail(ComponentCenteringAnalyzer::describe($violation));
        }

        // Guards the whole check against silently passing on an empty set, should the parsing ever stop finding anything
        self::assertGreaterThan(5, count($result['centered']), 'No centered component was found at all, the stylesheet or the templates are no longer being read.');
        self::assertContains('.hero.hero--has-bg', $result['breakouts'], 'The full-bleed sections are no longer read as breakouts, so nothing is checking their negative margins.');
    }
}
