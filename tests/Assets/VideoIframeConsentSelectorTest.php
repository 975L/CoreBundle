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

// video-iframe.js decides whether the page has a consent banner by querying a Stimulus identifier it does
// not own - c975l/site-bundle registers its banner as "cookie-consent", and the controller used to look
// for "cookieConsent". Nothing fails loudly on a mismatch: the query simply returns null, connect() takes
// the "no banner on this page" branch and renders the iframe immediately, loading YouTube's ~1 MB player
// before any consent has been given. This locks the selector to both spellings so the casing can't drift
// apart again. Same idea as CaptchaControllerDataAttributesTest, for a cross-bundle contract rather than
// a controller's own value attributes.
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
        $js = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('document.querySelector(CONSENT_BANNER_SELECTOR)', $js);
        $this->assertSame(1, substr_count($js, 'document.querySelector('), sprintf('"%s" queries the document more than once, the consent check must stay the only one.', self::CONTROLLER_JS));
    }

    // The player is only worth loading once it is about to be seen - dropping the observer would put ~1 MB of third-party JavaScript back on every page load carrying a video
    public function testIframeInjectionIsDeferredUntilTheElementNearsTheViewport(): void
    {
        $js = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('new IntersectionObserver(', $js);
        $this->assertStringContainsString('rootMargin: ROOT_MARGIN', $js);
        $this->assertMatchesRegularExpression('/const ROOT_MARGIN = "\d+px";/', $js);
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
