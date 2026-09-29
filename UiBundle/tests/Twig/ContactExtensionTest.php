<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Registry\SameAsRegistry;
use c975L\UiBundle\Service\ContactSnippetBuilder;
use c975L\UiBundle\Service\GoogleMapsLinkBuilder;
use c975L\UiBundle\Service\JsonLdBuilder;
use c975L\UiBundle\Twig\ContactExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AttributeExtension;

class ContactExtensionTest extends TestCase
{
    private function extension(): ContactExtension
    {
        return new ContactExtension(new ContactSnippetBuilder(new SameAsRegistry(), new JsonLdBuilder()), new GoogleMapsLinkBuilder());
    }

    // Names locked: templates/components/Contact/Details.html.twig calls both, and a rename would fail there silently
    public function testExposesTheFunctionsTheContactComponentCalls(): void
    {
        // Sorted rather than read in order: the attributes are collected in the methods' declaration order, which is no part of the contract
        $names = array_map(static fn ($function) => $function->getName(), new AttributeExtension(ContactExtension::class)->getFunctions());
        sort($names);

        $this->assertSame(['contact_json_ld', 'contact_week', 'google_maps_url'], $names);
    }

    // The payload is escaped by the builder, so it is printed as-is rather than re-escaped by Twig
    public function testJsonLdIsMarkedHtmlSafe(): void
    {
        $functions = [];
        foreach (new AttributeExtension(ContactExtension::class)->getFunctions() as $function) {
            $functions[$function->getName()] = $function;
        }

        $this->assertArrayHasKey('contact_json_ld', $functions);
        $this->assertContains('html', $functions['contact_json_ld']->getSafe(new \Twig\Node\EmptyNode()));
    }

    public function testJsonLdReturnsTheEncodedGraph(): void
    {
        $this->assertSame('Garage Central', json_decode($this->extension()->jsonLd(['name' => 'Garage Central']), true)['name']);
        $this->assertSame('', $this->extension()->jsonLd([]));
    }

    // One line per day, the whole week: a range lands on every day it names, and a day none names stays empty - closed
    public function testEveryDayOfTheWeekGetsItsOwnRanges(): void
    {
        $week = $this->extension()->week([
            ['days' => ['Monday', 'Tuesday'], 'opens' => '14:00', 'closes' => '18:00'],
            ['days' => ['Tuesday'], 'opens' => '08:00', 'closes' => '12:00'],
        ]);

        $this->assertSame(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], array_keys($week));
        $this->assertSame([['opens' => '14:00', 'closes' => '18:00']], $week['Monday']);
        $this->assertSame([], $week['Sunday']);
    }

    // Earliest first whatever the entry order, a hand-typed "9:00" included
    public function testRangesOfADayAreSortedByOpeningTime(): void
    {
        $week = $this->extension()->week([
            ['days' => ['Monday'], 'opens' => '14:00', 'closes' => '18:00'],
            ['days' => ['Monday'], 'opens' => '9:00', 'closes' => '12:00'],
        ]);

        $this->assertSame(['9:00', '14:00'], array_column($week['Monday'], 'opens'));
    }

    public function testUnknownDaysAndRowsWithoutTimesAreIgnored(): void
    {
        $week = $this->extension()->week([
            ['days' => ['Monday', 'Caturday'], 'opens' => '09:00', 'closes' => '12:00'],
            ['days' => ['Tuesday'], 'opens' => '', 'closes' => '12:00'],
        ]);

        $this->assertCount(7, $week);
        $this->assertCount(1, $week['Monday']);
        $this->assertSame([], $week['Tuesday']);
    }
}
