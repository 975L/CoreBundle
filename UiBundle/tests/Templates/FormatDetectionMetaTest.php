<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;

// The tag lived in SiteBundle's layout until that one handed its <head> over to this one, where it went missing for a release - nothing was checking for it. It belongs to the page shell every c975L site inherits, so it is asserted on the template that actually ships
class FormatDetectionMetaTest extends TestCase
{
    // Left out, iOS turns anything shaped like a number - a date, a reference, a price - into a tappable phone link, restyled and unclickable for what it really points at
    public function testTheLayoutTellsIosNotToLinkNumbersAsPhoneNumbers(): void
    {
        $this->assertStringContainsString(
            '<meta name="format-detection" content="telephone=no">',
            (string) file_get_contents(dirname(__DIR__, 2) . '/templates/layout.html.twig'),
            'iOS is free again to turn every date and price of the site into a phone link.'
        );
    }
}
