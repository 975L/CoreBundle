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

// An <img>'s width/height HTML attributes map to CSS *presentational hints* on the width/height properties.
// A stylesheet that shapes an image with "aspect-ratio" but never declares "height" therefore leaves the
// hint in charge: height="800" wins, the box stretches to 800px and the aspect-ratio is silently ignored.
// That is exactly what blew up every hero the day the components started writing their intrinsic pixel
// size, so this locks the pairing: an img rule setting aspect-ratio must also settle its own height.
class ImageAspectRatioHeightTest extends TestCase
{
    public function testEveryImgRuleSettingAspectRatioAlsoSettlesItsHeight(): void
    {
        $checked = 0;

        foreach (glob(dirname(__DIR__, 2) . '/sass/*.scss') as $path) {
            foreach ($this->imgRules((string) file_get_contents($path)) as $selector => $body) {
                if (!str_contains($body, 'aspect-ratio')) {
                    continue;
                }

                ++$checked;

                $this->assertMatchesRegularExpression(
                    '/(^|[;{\s])height\s*:/',
                    $body,
                    sprintf('"%s" shapes "%s" with an aspect-ratio but declares no height, so an img\'s height attribute overrides it.', basename($path), $selector)
                );
            }
        }

        $this->assertGreaterThan(0, $checked, 'No img rule with an aspect-ratio found at all, the test itself is broken.');
    }

    // Returns the declaration blocks whose selector targets the img *element* - a "-img" suffixed class
    // name (.portfolio-grid__project-img, a wrapper <div>) is not one, hence the word boundary below
    private function imgRules(string $scss): array
    {
        $scss = (string) preg_replace(['#//[^\n]*#', '#/\*.*?\*/#s'], '', $scss);
        $rules = [];

        preg_match_all('/([^{}\n][^{}]*)\{([^{}]*)\}/', $scss, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $selector = trim($match[1]);

            if (preg_match('/(^|[\s>+~])img\b/', $selector)) {
                $rules[$selector] = $match[2];
            }
        }

        return $rules;
    }
}
