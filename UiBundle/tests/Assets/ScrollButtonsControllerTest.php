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

// Guards assets/js/scroll-buttons.js, which cancels the click it handles: what it takes for an anchor never reaches the server, and the repository has no browser to tell it wrong
class ScrollButtonsControllerTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/scroll-buttons.js';
    private const string BARREL = 'assets/controllers.js';
    private const string COMPONENT = 'templates/components/Scroll/Buttons.html.twig';
    private const string STYLESHEET = 'public/css/styles.css';
    private const string TOKENS = 'sass/_tokens.scss';
    private const string IDENTIFIER = 'scrollButtons';

    // Lazily registered like the other page-kind controllers, the barrel only loading what the document asks for
    public function testTheControllerIsRegisteredAsLazy(): void
    {
        $this->assertStringContainsString(
            sprintf("%s: () => import('./js/scroll-buttons.js'),", self::IDENTIFIER),
            $this->read(self::BARREL)
        );
    }

    // The component is the other end of the contract: the identifier it writes is what the barrel registers, and the targets are what the controller shows and hides
    public function testTheComponentWritesTheIdentifierAndItsTargets(): void
    {
        $component = $this->read(self::COMPONENT);

        $this->assertStringContainsString(sprintf('data-controller="%s"', self::IDENTIFIER), $component);
        $this->assertStringContainsString(sprintf('data-%s-target="top"', self::IDENTIFIER), $component);
        $this->assertStringContainsString(sprintf('data-%s-target="bottom"', self::IDENTIFIER), $component);
    }

    // A shop ordered by price, a listing on its second page: those links end on "#products" and share the path of the page they are on, and cancelling them left the listing exactly as it was
    public function testALinkChangingTheQueryIsNotTakenForAnAnchor(): void
    {
        $this->assertStringContainsString(
            'url.search !== window.location.search',
            $this->read(self::CONTROLLER_JS),
            'The controller takes a link that only changes the query for a same-page anchor, and cancels the navigation it asks for.'
        );
    }

    // Behind a misconfigured proxy, an absolute link can carry another origin than the page it sits on: pushState refuses it, and the exception thrown after preventDefault() left the scroll button inert
    public function testALinkOfAnotherOriginIsNotTakenForAnAnchor(): void
    {
        $this->assertStringContainsString(
            'url.origin !== window.location.origin',
            $this->read(self::CONTROLLER_JS),
            'The controller takes a link of another origin for a same-page anchor, and pushState throws on the click it has already cancelled.'
        );
    }

    // Every page carries a <base href>, against which a relative url resolves: pushing the hash alone left the address at the root of the site, path and query gone
    public function testTheWholeAddressIsPushed(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('history.pushState(null, "", url.href);', $controller);
        $this->assertStringNotContainsString('history.pushState(null, "", url.hash);', $controller);
    }

    // A listing growing on scroll pushes the bottom of the page away from the scroll heading for it, and the button pulling down landed in the middle of it
    public function testAnAnchorScrollIsAnnouncedToWhatGrowsOnThePage(): void
    {
        $this->assertStringContainsString(
            'document.dispatchEvent(new CustomEvent("anchor:scroll"));',
            $this->read(self::CONTROLLER_JS),
            'The controller scrolls to an anchor without announcing it, and a listing loading its next page moves the target away.'
        );
    }

    // The buttons are laid out by the classes the controller toggles, an inline style being what a nonced style-src drops: a class carrying no display rule leaves them hidden for good
    public function testTheStylesheetLaysTheButtonsOutOnTheClassesToggled(): void
    {
        $stylesheet = $this->read(self::STYLESHEET);

        $this->assertMatchesRegularExpression('/a\.backTop\.fade-in[^{]*\{[^}]*display: flex/s', $stylesheet);
        $this->assertMatchesRegularExpression('/a\.backTop\.fade-out[^{]*\{[^}]*pointer-events: none/s', $stylesheet);
    }

    // The pair shares the bottom-right corner, the one pulling up stacked over the other: the bottom-left is where a cookie banner and a basket bar land, and only this side is offset by --bottom-bar-height
    public function testBothButtonsSitInTheBottomRightCorner(): void
    {
        $stylesheet = $this->read(self::STYLESHEET);

        $this->assertMatchesRegularExpression('/a\.pullDown,\s*\na\.backTop \{[^}]*right: 25px/s', $stylesheet);
        $this->assertMatchesRegularExpression('/a\.backTop \{[^}]*bottom: calc\(25px \+ var\(--back-pull-size\)/s', $stylesheet);
        $this->assertStringNotContainsString('left: 25px', $stylesheet, 'A button is back in the bottom-left corner, which a cookie banner and a basket bar already hold.');
    }

    // 0.7 and not lower: the filter dims the arrow with its disc, and below that the pair falls under the 3:1 WCAG 1.4.11 asks of an interface element - a hover lifting it rescues no touch screen
    public function testTheRestingOpacityStaysAboveTheContrastThreshold(): void
    {
        $this->assertMatchesRegularExpression(
            '/--back-pull-opacity: 0\.7;/',
            $this->read(self::TOKENS),
            'The resting opacity moved: below 0.7 the arrow no longer reaches 3:1 against its own disc.'
        );

        $this->assertMatchesRegularExpression('/a\.pullDown,\s*\na\.backTop \{[^}]*filter: opacity\(var\(--back-pull-opacity\)\)/s', $this->read(self::STYLESHEET));
    }

    // The arrow follows --back-pull-color instead of staying the black a png was drawn in, and the two files it used to be are deleted: an unresolvable url() fails the compilation of every bundle stylesheet at once
    public function testTheArrowIsAnInlineSvgAndNoPngIsNamedAnyMore(): void
    {
        $component = $this->read(self::COMPONENT);

        $this->assertStringContainsString('stroke="currentColor"', $component);
        $this->assertStringNotContainsString('up-arrow.png', $component);
        $this->assertStringNotContainsString('down-arrow.png', $component);
        $this->assertStringNotContainsString('up-arrow.png', $this->read(self::STYLESHEET));
        $this->assertFileDoesNotExist(\dirname(__DIR__, 2) . '/public/images/up-arrow.png');
        $this->assertFileDoesNotExist(\dirname(__DIR__, 2) . '/public/images/down-arrow.png');
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
