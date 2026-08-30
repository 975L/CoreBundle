<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// A hidden block renders nothing (BlockExtension::renderBlock, covered by its own test), but a page's own run has to drop it earlier than that: the card grouping around it counts kinds, not rendered html, so a hidden card left in the list opens a ".cards" row that then gets nothing to hold
class HiddenBlockMarkupTest extends TestCase
{
    private const string TEMPLATE = '/templates/components/Blocks/Blocks.html.twig';

    public function testThePagesRunDropsTheBlocksItsEditorSetAside(): void
    {
        $this->assertStringContainsString(
            '{% set blocks = []|merge(blocks|filter(b => not b.hidden)) %}',
            $this->template(),
            'A hidden block is rendered by the page again, so it reaches the card grouping and the edit-url query below.'
        );
    }

    // "filter" yields a generator, which loop.last is not offered over - the merge is what makes the sequence countable again
    public function testTheFilteredBlocksAreMergedBackIntoAnArray(): void
    {
        $this->assertStringContainsString('[]|merge(blocks|filter(', $this->template());
    }

    public function testTheBlocksAreFilteredBeforeTheirKindsAreCounted(): void
    {
        $template = $this->template();

        $filter = strpos($template, '{% set blocks = []|merge(');
        $kinds = strpos($template, '{% set kinds = blocks|map(');

        $this->assertIsInt($filter);
        $this->assertIsInt($kinds);
        $this->assertLessThan($kinds, $filter, 'The kinds are counted before the hidden blocks are dropped, so a hidden card still opens an empty ".cards" row.');
    }

    /**
     * The containers laying their slots out in a cell of their own, plus the one counting them before opening a row.
     *
     * @return array<string, array{string}>
     */
    public static function containerProvider(): array
    {
        return [
            'flex columns' => ['/templates/components/Section/FlexColumns.html.twig'],
            'section cards' => ['/templates/components/Section/Cards.html.twig'],
            'video grid' => ['/templates/components/Video/Grid.html.twig'],
        ];
    }

    // A slot renders nothing, but its cell is written by the container around it: left in, a hidden slot holds an empty column open - and "slots|length" would still open a row for a container whose every slot is set aside
    #[DataProvider('containerProvider')]
    public function testAContainerDropsTheSlotsItsEditorSetAside(string $file): void
    {
        $this->assertStringContainsString(
            '{% set slots = []|merge(slots|filter(s => not s.hidden)) %}',
            $this->read($file),
            $file . ' lays out its slots without dropping the hidden ones, so one of them holds an empty cell open.'
        );
    }

    private function template(): string
    {
        return $this->read(self::TEMPLATE);
    }

    private function read(string $file): string
    {
        $path = \dirname(__DIR__, 2) . $file;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
