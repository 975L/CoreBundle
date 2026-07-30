<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\WhatsNewProvider;
use PHPUnit\Framework\TestCase;

// The provider reads a fixed path (dirname(__DIR__, 2) . '/config/whatsnew.json' relative to tests/Service), i.e. this bundle's own real config/whatsnew.json - there is no way to inject a fixture file. Assertions below therefore parse that same real file rather than hardcoding its content, so the tests stay valid as entries are appended over time.
class WhatsNewProviderTest extends TestCase
{
    private ?string $originalLocale = null;

    protected function setUp(): void
    {
        $this->originalLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->originalLocale);
    }

    private function decodeRealWhatsNewJson(): array
    {
        $file = \dirname(__DIR__, 2) . '/config/whatsnew.json';

        return json_decode(file_get_contents($file), true) ?? [];
    }

    public function testGetEntriesReturnsOneEntryPerJsonRow(): void
    {
        $provider = new WhatsNewProvider();
        $raw = $this->decodeRealWhatsNewJson();

        $this->assertCount(count($raw), $provider->getEntries());
    }

    public function testGetEntriesParsesDateAsDateTimeImmutable(): void
    {
        $provider = new WhatsNewProvider();
        $raw = $this->decodeRealWhatsNewJson();
        $entries = $provider->getEntries();

        $this->assertInstanceOf(\DateTimeImmutable::class, $entries[0]['date']);
        $this->assertSame($raw[0]['date'], $entries[0]['date']->format('Y-m-d'));
    }

    // With the current locale set to English, each description resolves to its "en" translation
    public function testGetEntriesResolvesDescriptionForCurrentLocale(): void
    {
        \Locale::setDefault('en');

        $provider = new WhatsNewProvider();
        $raw = $this->decodeRealWhatsNewJson();
        $entries = $provider->getEntries();

        $this->assertSame($raw[0]['description'][0]['en'], $entries[0]['description'][0]);
    }

    public function testGetEntriesResolvesDescriptionForFrenchLocale(): void
    {
        \Locale::setDefault('fr');

        $provider = new WhatsNewProvider();
        $raw = $this->decodeRealWhatsNewJson();
        $entries = $provider->getEntries();

        $this->assertSame($raw[0]['description'][0]['fr'], $entries[0]['description'][0]);
    }

    // The real file always has an "en" translation - an unknown locale falls back to it
    public function testGetEntriesFallsBackToEnglishForUnknownLocale(): void
    {
        \Locale::setDefault('xx_XX');

        $provider = new WhatsNewProvider();
        $raw = $this->decodeRealWhatsNewJson();
        $entries = $provider->getEntries();

        $this->assertSame($raw[0]['description'][0]['en'], $entries[0]['description'][0]);
    }
}
