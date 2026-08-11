<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\LayoutAuditor;
use PHPUnit\Framework\TestCase;

// Locks what the measuring script looks for, no browser involved: the script is a string until Chrome runs it
class LayoutAuditorTest extends TestCase
{
    // Each one caught a real defect this bundle shipped, and dropping one silently would go unnoticed
    private const array CHECKS = ['overflow', 'centering', 'image-size'];

    public function testTheScriptStillCarriesEveryCheck(): void
    {
        $script = $this->script();

        foreach (self::CHECKS as $check) {
            $this->assertStringContainsString(sprintf("check: '%s'", $check), $script, sprintf('The "%s" check is gone from the measuring script.', $check));
        }
    }

    // The one thing the browser layer knows that the sass tests do not is which rule meant to centre what. Read off the element instead, a centering already lost computes to "0px" and reports a clean page - which is precisely how this check first failed to see the slider.
    public function testCenteringIsReadFromTheStylesheetRatherThanTheElement(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('document.styleSheets', $script);
        $this->assertStringContainsString('rule.style.marginLeft', $script);
    }

    // A cross-origin stylesheet throws on cssRules, and would otherwise take the whole audit down with it
    public function testStylesheetReadingIsGuarded(): void
    {
        $this->assertMatchesRegularExpression('/try\s*\{\s*rules = sheet\.cssRules/', $this->script());
    }

    public function testTheDefaultWidthsCoverBothEndsOfTheBreakpoints(): void
    {
        $this->assertLessThan(621, min(LayoutAuditor::DEFAULT_WIDTHS));
        $this->assertGreaterThan(1025, max(LayoutAuditor::DEFAULT_WIDTHS));
    }

    // Without it the block animations park their blocks off-screen and every one reads as an overflow
    public function testThePageIsSettledBeforeBeingMeasured(): void
    {
        $settle = $this->method('settleScript');

        $this->assertStringContainsString('animation-duration: 0s', $settle);
        $this->assertStringContainsString('window.scrollTo', $settle);
    }

    public function testAvailabilityFollowsTheOptionalDependency(): void
    {
        $this->assertSame(class_exists(\HeadlessChromium\BrowserFactory::class), LayoutAuditor::isAvailable());
    }

    private function script(): string
    {
        return $this->method('script');
    }

    private function method(string $name): string
    {
        $method = new \ReflectionMethod(LayoutAuditor::class, $name);

        return (string) $method->invoke(new LayoutAuditor());
    }
}
