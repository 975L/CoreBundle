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

// What a component nests is rendered from the component's own context, props included: an adapter reading "content" inside a card it nests into gets the card's empty prop rather than its own block data, silently and with no error at all. Read statically rather than rendered, these template tests running on a bare Environment that writes the "<twig:...>" calls out as text and so has no component context to shadow anything in (see CollectionItemStatVariantTest) - exactly why nothing here saw it happen
class BlockAdapterNestedContextTest extends TestCase
{
    private const string COMPONENT = 'c975LUi:Card:Card';

    // Every block adapter nesting its data inside a card reads that data under a name of its own, never one the card declares
    public function testNoBlockAdapterReadsAVariableTheNestedCardShadows(): void
    {
        $props = $this->componentProps();
        $this->assertNotEmpty($props, 'The card declares no prop at all, so this test reads nothing');

        foreach (glob(\dirname(__DIR__, 2) . '/templates/blocks/*.html.twig') ?: [] as $path) {
            foreach ($this->nestedCards((string) file_get_contents($path)) as [$given, $body]) {
                // A prop the caller passes carries the caller's own value; only the ones left out fall back to the component's default
                foreach (array_diff($props, $given) as $shadowed) {
                    $this->assertDoesNotMatchRegularExpression(
                        '/\{[{%][^}]*\b' . preg_quote($shadowed, '/') . '\b/',
                        $body,
                        basename($path) . ' reads "' . $shadowed . '" inside the card it nests into, where the card\'s own empty prop takes its place'
                    );
                }
            }
        }
    }

    // The names declared by the component's "{% props %}", with their defaults dropped; @return list<string>
    private function componentProps(): array
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/components/Card/Card.html.twig');
        if (!preg_match('/\{%\s*props\s(.*?)%\}/s', $source, $matches)) {
            return [];
        }

        $props = [];
        foreach (explode(',', $matches[1]) as $prop) {
            $name = trim(explode('=', $prop)[0]);
            if ('' !== $name) {
                $props[] = $name;
            }
        }

        return $props;
    }

    // One entry per non self-closing card call in the template: the attribute names it is given, and everything it nests; @return list<array{0: list<string>, 1: string}>
    private function nestedCards(string $source): array
    {
        $calls = [];
        $offset = 0;
        $open = '<twig:' . self::COMPONENT;
        $close = '</twig:' . self::COMPONENT . '>';

        while (false !== ($start = strpos($source, $open, $offset))) {
            $offset = $start + \strlen($open);
            $tagEnd = $this->endOfOpeningTag($source, $offset);
            $tag = substr($source, $offset, $tagEnd - $offset);

            // Self-closing: a card given everything it draws, nesting nothing there is to shadow
            if (str_ends_with(rtrim($tag), '/')) {
                continue;
            }

            $bodyEnd = strpos($source, $close, $tagEnd);
            if (false === $bodyEnd) {
                continue;
            }

            preg_match_all('/([a-zA-Z0-9_-]+)\s*=/', $tag, $attributes);
            $calls[] = [$attributes[1], substr($source, $tagEnd + 1, $bodyEnd - $tagEnd - 1)];
            $offset = $bodyEnd;
        }

        return $calls;
    }

    // The ">" actually closing the opening tag, skipping the ones written inside an attribute's own value (an arrow function, a comparison)
    private function endOfOpeningTag(string $source, int $offset): int
    {
        $quote = null;
        for ($position = $offset; $position < \strlen($source); ++$position) {
            $character = $source[$position];
            if ('"' === $character || "'" === $character) {
                $quote = $quote === $character ? null : ($quote ?? $character);
            } elseif ('>' === $character && null === $quote) {
                return $position;
            }
        }

        return \strlen($source);
    }
}
