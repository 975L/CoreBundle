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

// The two structural constraints this controller rests on and that no scenario can put to the test: that the banner is only ever asked through the consent adapter, and that the player is scheduled from the one branch where there is nothing to look at
// Everything else it used to assert as lines of source - the selector matching both spellings, the empty src, the player's own attributes, the prompt, the click re-read against the current answer - is VideoIframeBehaviourTest's, run in a browser
class VideoIframeConsentSelectorTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/video-iframe.js';

    // The banner is asked through consent.js alone, the adapter a site running another banner registers its own with: a query of the document or a banner's global here puts the coupling straight back
    public function testTheBannerIsAskedThroughTheSharedAdapterOnly(): void
    {
        $script = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('import * as consent from "./consent.js";', $script);
        $this->assertStringContainsString('consent.isAccepted("content")', $script);
        $this->assertStringNotContainsString('consent.hasBanner()', $script, 'A page with no banner frames its player unasked, where consent.js asks in place.');
        $this->assertStringNotContainsString('document.querySelector(', $script, sprintf('"%s" queries the document itself, where the consent adapter is the only one to.', self::CONTROLLER_JS));
        $this->assertStringNotContainsString('CookieConsent', $script);
    }

    // A poster is what the click-to-play gate is made of, and the scheduling has to stay in the one branch where there is none: a second call anywhere else puts a grid of six players back on the initial scroll, and a scenario watching one component would see nothing wrong with it
    public function testThePlayerIsScheduledFromTheOneBranchWithNothingToLookAt(): void
    {
        $script = $this->read(self::CONTROLLER_JS);

        $this->assertMatchesRegularExpression('/if \(!this\.hasPlayTarget\) \{\s*this\.scheduleIframe\(\);\s*return;/', $script);
        $this->assertSame(1, substr_count($script, 'this.scheduleIframe();'), sprintf('"%s" schedules its iframe outside the no-poster branch, which loads the player a poster is there to hold back.', self::CONTROLLER_JS));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
