<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// The page served while the site is down carries no external asset at all - it has to render with everything else cut off - so its styling is a local <style>. Which a nonce'd style-src only authorizes if that element carries the nonce, and csp_nonce() only exists once a site has configured a csp section: the two cases below are both real, and the page must render in either.
class MaintenancePageStyleTest extends TestCase
{
    // A nonce present on style-src makes 'unsafe-inline' a no-op, so without this the page renders unstyled on every site having a CSP
    public function testTheStyleElementIsNoncedWhenTheSiteHasACsp(): void
    {
        $html = $this->render(true);

        $this->assertStringContainsString('<style nonce="style">', $html, 'The maintenance page no longer nonces its style element, so a CSP drops it.');
    }

    // csp_nonce() is NelmioSecurityBundle's, and it declares it only for a configured csp section - breaking here would take the site down at the very moment it is already down
    public function testThePageStillRendersWithNoNonceFunctionAvailable(): void
    {
        $html = $this->render(false);

        $this->assertStringContainsString('<style >', $html, 'The style element is gone along with the nonce.');
        $this->assertStringNotContainsString('nonce=', $html);
        $this->assertStringContainsString('label.maintenance', $html, 'The page itself no longer renders.');
    }

    // The trans filter is the framework's, csp_nonce() NelmioSecurityBundle's - neither bundle is booted here. The shipped source is loaded under a name of its own per case: "guard" is resolved when the template is compiled, and Twig names a compiled class after the template name alone, so one name would have the second case reuse the first one's compilation
    private function render(bool $withNonce): string
    {
        $name = $withNonce ? 'maintenance-nonced' : 'maintenance-bare';
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/maintenance/index.html.twig');

        $twig = new Environment(new ArrayLoader([$name => $source]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $id): string => $id));

        if ($withNonce) {
            $twig->addFunction(new TwigFunction('csp_nonce', static fn (string $directive): string => $directive));
        }

        return $twig->render($name, []);
    }
}
