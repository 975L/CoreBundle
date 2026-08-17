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
use c975L\UiBundle\Twig\ContactExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AttributeExtension;

class ContactExtensionTest extends TestCase
{
    private function extension(): ContactExtension
    {
        return new ContactExtension(new ContactSnippetBuilder(new SameAsRegistry()));
    }

    // Names locked: templates/components/Contact/Details.html.twig calls both, and a rename would fail there silently
    public function testExposesTheFunctionsTheContactComponentCalls(): void
    {
        // Sorted rather than read in order: the attributes are collected in the methods' declaration order, which is no part of the contract
        $names = array_map(static fn ($function) => $function->getName(), new AttributeExtension(ContactExtension::class)->getFunctions());
        sort($names);

        $this->assertSame(['contact_day_runs', 'contact_json_ld'], $names);
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
        $this->assertSame('Autotech', json_decode($this->extension()->jsonLd(['name' => 'Autotech']), true)['name']);
        $this->assertSame('', $this->extension()->jsonLd([]));
    }

    // So "Monday…Friday 9:00-12:00" reads as one line instead of five
    public function testConsecutiveDaysCollapseIntoOneRun(): void
    {
        $this->assertSame(
            [['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']],
            $this->extension()->dayRuns(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])
        );
    }

    public function testAGapStartsANewRunAndTheWeekOrderIsRestored(): void
    {
        $this->assertSame(
            [['Monday', 'Tuesday'], ['Thursday'], ['Saturday', 'Sunday']],
            $this->extension()->dayRuns(['Saturday', 'Thursday', 'Monday', 'Sunday', 'Tuesday'])
        );
    }

    public function testUnknownDaysAreIgnored(): void
    {
        $this->assertSame([['Monday']], $this->extension()->dayRuns(['Monday', 'Caturday']));
        $this->assertSame([], $this->extension()->dayRuns([]));
    }
}
