<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use PHPUnit\Framework\TestCase;

class SiteLocalesTest extends TestCase
{
    // The no-regression contract: a site that declared no language speaks the one it was written in, and everything reading this finds a single locale
    public function testASiteDeclaringNoLanguageOffersItsDefaultOneAlone(): void
    {
        $siteLocales = new SiteLocales([], 'fr');

        $this->assertSame(['fr'], $siteLocales->all());
        $this->assertSame([], $siteLocales->translatable());
        $this->assertFalse($siteLocales->isMultilingual());
    }

    // "framework.enabled_locales", where Symfony itself reads which catalogues to compile and which "_locale" a route may take
    public function testTheLanguagesTheFrameworkWasGivenAreTheOnesOffered(): void
    {
        $siteLocales = new SiteLocales(['fr', 'en', 'es'], 'fr');

        $this->assertSame(['fr', 'en', 'es'], $siteLocales->all());
        $this->assertSame(['en', 'es'], $siteLocales->translatable());
        $this->assertTrue($siteLocales->isMultilingual());
    }

    // The default language is what every untranslated value already is, so it is offered whether or not the list names it
    public function testTheDefaultLanguageIsOfferedEvenWhenTheListLeavesItOut(): void
    {
        $siteLocales = new SiteLocales(['en', 'es'], 'fr');

        $this->assertSame(['fr', 'en', 'es'], $siteLocales->all());
        $this->assertSame(['en', 'es'], $siteLocales->translatable());
    }

    // Named twice in the yaml, offered once: the language menu would otherwise show it twice
    public function testALanguageNamedTwiceIsOfferedOnce(): void
    {
        $this->assertSame(['fr', 'en'], new SiteLocales(['fr', 'en', 'fr'], 'fr')->all());
    }

    // A typo would otherwise reach EasyAdmin's Locale::new(), which throws - and takes down every back-office page, the screens that would fix it included
    public function testALanguageTheIntlCatalogueDoesNotKnowIsDropped(): void
    {
        $this->assertSame(['fr', 'en', 'es'], new SiteLocales(['en', 'not-a-language', 'es'], 'fr')->all());
    }

    // Asked on every front request, from a kernel.request listener
    public function testTheAnswerIsBuiltOnlyOncePerRequest(): void
    {
        $siteLocales = new SiteLocales(['fr', 'en'], 'fr');

        $this->assertSame($siteLocales->all(), $siteLocales->all());
        $this->assertSame('fr', $siteLocales->getDefaultLocale());
    }
}
