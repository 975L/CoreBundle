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

// A casing mismatch on the banner's identifier returns null and loads YouTube before any consent
class VideoIframeConsentSelectorTest extends TestCase
{
    private const CONTROLLER_JS = 'assets/js/video-iframe.js';

    // Every spelling a consent banner may register itself under - "cookie-consent" is the one c975l/site-bundle's General:CookieConsent component writes
    private const IDENTIFIERS = ['cookie-consent', 'cookieConsent'];

    public function testConsentSelectorMatchesEveryAcceptedIdentifier(): void
    {
        $selector = $this->consentSelector();

        foreach (self::IDENTIFIERS as $identifier) {
            $this->assertStringContainsString(
                sprintf('[data-controller~="%s"]', $identifier),
                $selector,
                sprintf('"%s" never matches a banner registered as "%s", so the iframe would render without consent.', self::CONTROLLER_JS, $identifier)
            );
        }
    }

    // A hardcoded querySelector() argument bypasses the constant the test above checks, putting the mismatch straight back
    public function testConnectQueriesTheSharedSelectorConstant(): void
    {
        $script = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('document.querySelector(CONSENT_BANNER_SELECTOR)', $script);
        $this->assertSame(1, substr_count($script, 'document.querySelector('), sprintf('"%s" queries the document more than once, the consent check must stay the only one.', self::CONTROLLER_JS));
    }

    // The player is only worth loading once it is about to be seen - dropping the observer would put ~1 MB of third-party JavaScript back on every page load carrying a video
    public function testIframeInjectionIsDeferredUntilTheElementNearsTheViewport(): void
    {
        $script = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('new IntersectionObserver(', $script);
        $this->assertStringContainsString('rootMargin: ROOT_MARGIN', $script);
        $this->assertMatchesRegularExpression('/const ROOT_MARGIN = "\d+px";/', $script);
    }

    // An empty src is not "no iframe": the browser resolves it on the document's own URL, so the page ends up loading inside itself. Reachable from a block saved without a video and from the showcase fixture when the app declares no placeholder media
    public function testNoIframeIsRenderedWithoutASrc(): void
    {
        $script = $this->read(self::CONTROLLER_JS);

        $this->assertMatchesRegularExpression('/if \(!this\.srcValue\) \{\s*return;/', $script, sprintf('"%s" renders its iframe whatever the src value, and an empty one loads the page inside itself.', self::CONTROLLER_JS));
    }

    // The two attributes a player needs to actually play once framed - dropped once already when this controller took over from the hand-written <iframe> tags, which cost the platforms' media servers the domain they check before serving a file
    public function testTheFramedPlayerIsGivenWhatItNeedsToPlay(): void
    {
        $script = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('iframe.allow = ALLOW;', $script);
        $this->assertStringContainsString('iframe.referrerPolicy = REFERRER_POLICY;', $script);
        $this->assertStringContainsString('const REFERRER_POLICY = "strict-origin-when-cross-origin";', $script);
    }

    // Autoplay is granted by the framing page, not asked for by the src alone: leaving it out of the list is what keeps a player from starting on its own at a returning visitor who merely scrolls past it
    public function testTheFramedPlayerIsNotAllowedToAutoplay(): void
    {
        preg_match('/const ALLOW = "([^"]+)";/', $this->read(self::CONTROLLER_JS), $matches);
        $this->assertNotEmpty($matches, sprintf('No ALLOW constant found in "%s".', self::CONTROLLER_JS));

        $this->assertStringNotContainsString('autoplay', $matches[1]);
    }

    // "const CONSENT_BANNER_SELECTOR = '...';" -> "..."
    private function consentSelector(): string
    {
        preg_match("/const CONSENT_BANNER_SELECTOR = '([^']+)';/", $this->read(self::CONTROLLER_JS), $matches);
        $this->assertNotEmpty($matches, sprintf('No CONSENT_BANNER_SELECTOR constant found in "%s".', self::CONTROLLER_JS));

        return $matches[1];
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
