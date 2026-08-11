<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Form\Block\FlexColumnType;
use PHPUnit\Framework\TestCase;

// The width comes from stored block data, so it may only build a class it was matched against first
class FlexColumnWidthMarkupTest extends TestCase
{
    private const string TEMPLATE = 'components/Section/FlexColumns.html.twig';

    public function testTheRowWhitelistsTheStoredValueBeforeBuildingTheClass(): void
    {
        $twig = $this->template();

        $this->assertMatchesRegularExpression(
            "/columnWidth\|default\('?'?\) in \[[^\]]+\]/",
            $twig,
            sprintf('"%s" must whitelist the stored width before turning it into a class.', self::TEMPLATE)
        );
    }

    // One single place builds the class, right after that check - no other occurrence of the prefix
    public function testOnlyTheWhitelistedValueEverBuildsTheClass(): void
    {
        $this->assertSame(
            1,
            substr_count($this->template(), 'flex-columns__col--'),
            sprintf('"%s" writes the flex-columns__col-- prefix more than once, only the whitelisted set may build it.', self::TEMPLATE)
        );
    }

    // A fraction offered by the form but missing from the template silently renders as an even column
    public function testTheWhitelistHoldsExactlyTheWidthsTheFormOffers(): void
    {
        preg_match("/columnWidth\|default\('?'?\) in \[([^\]]+)\]/", $this->template(), $matches);

        $whitelisted = array_map(
            static fn (string $value): string => trim($value, " '"),
            explode(',', $matches[1] ?? '')
        );

        $this->assertSame(FlexColumnType::WIDTHS, $whitelisted);
    }

    // Same rule as the width above: the alignment comes from stored block data and may only build a class it was matched against first. "top" is deliberately absent - it is the row's own default and writes nothing, which is also what every row stored before the field existed renders as
    public function testTheRowWhitelistsTheStoredAlignmentBeforeBuildingTheClass(): void
    {
        $twig = $this->template();

        $this->assertStringContainsString("verticalAlign|default('') in ['middle', 'bottom']", $twig);
        $this->assertSame(
            1,
            substr_count($twig, 'flex-columns--'),
            sprintf('"%s" writes the flex-columns-- prefix more than once, only the whitelisted set may build it.', self::TEMPLATE)
        );
    }

    // A component supporting the option is useless if the block adapter never passes the stored value on
    public function testTheBlockAdapterPassesTheStoredAlignmentToItsComponent(): void
    {
        $path = \dirname(__DIR__, 2) . '/templates/blocks/FlexColumns.html.twig';
        $this->assertFileExists($path);

        $this->assertStringContainsString('verticalAlign="{{ verticalAlign|default(\'\') }}"', (string) file_get_contents($path));
    }

    // Comments document the mechanism and name the classes, which is not markup
    private function template(): string
    {
        $path = dirname(__DIR__, 2) . '/templates/' . self::TEMPLATE;
        $this->assertFileExists($path);

        return (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents($path));
    }
}
