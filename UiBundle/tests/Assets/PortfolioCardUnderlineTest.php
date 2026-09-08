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

// The same card is drawn by two templates that link it differently: the portfolio_grid component wraps the whole tile in one anchor, where a collection item in that presentation is a container carrying the picture's link and the title's. Both have to override the theme's "a:hover" underline, and a rule written for one of them alone leaves the other underlined - which no template test can see.
class PortfolioCardUnderlineTest extends TestCase
{
    // The component's own markup: one anchor around the whole card, whose underline would run under every line it holds
    public function testTheWholeCardAnchorIsNotUnderlined(): void
    {
        $scss = $this->scss();

        $this->assertMatchesRegularExpression('/\.portfolio-grid__project \{[^}]*text-decoration: none;/s', $scss);
        $this->assertMatchesRegularExpression('/\.portfolio-grid__project:hover,\s*\.portfolio-grid__project:visited:hover \{\s*text-decoration: none;/', $scss);
    }

    // The collection item's markup: the container is a <div>, and the two links inside it carry the destination
    public function testThePictureAndTheTitleLinksAreNotUnderlinedEither(): void
    {
        $scss = $this->scss();

        foreach (['.portfolio-grid__project-img > a', '.portfolio-grid__project-body > a'] as $selector) {
            $this->assertStringContainsString($selector . ',', $scss, sprintf('"%s" no longer overrides the theme\'s underline.', $selector));
            $this->assertStringContainsString($selector . ':hover', $scss, sprintf('"%s" is underlined again under the pointer.', $selector));
        }
    }

    private function scss(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/sass/_page-sections.scss');
    }
}
