<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Testing;

/**
 * Reads compiled stylesheets the way the cascade does - in load order, with each rule's specificity - so a
 * test can answer "does this rule beat that one on the same element" without a browser.
 *
 * Lives in src/ rather than tests/ on purpose: a bundle's tests/ is autoload-dev and never reaches the
 * bundles that depend on it, and a stylesheet is only meaningful next to the ones loaded with it. SiteBundle
 * runs the same engine over its own sheet plus this bundle's, which is where the cross-bundle collisions are.
 *
 * Never registered as a service (see the Testing/ exclusion in config/services.yaml): it is a test utility
 * that ships, in the same spirit as Symfony's own Test namespaces.
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
final class StylesheetCascade
{
    // Nested rules are deliberate responsive/state overrides; a layer always loses to the unlayered rules anyway
    private const array SKIPPED_AT_RULES = ['media', 'supports', 'container', 'layer', 'keyframes'];

    /**
     * @param list<array{selectors: list<string>, declarations: array<string, string>, specificity: list<int>, order: int, source: string}> $rules
     */
    private function __construct(
        private readonly array $rules,
    ) {
    }

    // Paths in the order the page loads them, source order deciding between two rules of equal specificity
    public static function fromFiles(string ...$paths): self
    {
        $rules = [];

        foreach ($paths as $path) {
            if (!is_file($path)) {
                throw new \RuntimeException(sprintf('"%s" is missing, the sass has not been compiled.', $path));
            }

            foreach (self::parse((string) file_get_contents($path)) as $rule) {
                $rules[] = $rule + ['order' => count($rules), 'source' => basename($path)];
            }
        }

        return new self($rules);
    }

    /**
     * @return list<array{selectors: list<string>, declarations: array<string, string>, specificity: list<int>, order: int, source: string}>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    // The classes whose own rule lays their children out, so a child's margin is theirs to set
    public function layoutContainerClasses(): array
    {
        $containers = [];

        foreach ($this->rules as $rule) {
            if (!in_array($rule['declarations']['display'] ?? '', ['flex', 'grid', 'inline-flex', 'inline-grid'], true)) {
                continue;
            }

            foreach ($rule['selectors'] as $selector) {
                if (preg_match('/^\.([a-z0-9_-]+)$/i', $selector, $matches)) {
                    $containers[$matches[1]] = true;
                }
            }
        }

        return $containers;
    }

    // The classes whose own rule makes them no box at all, so what they wrap is placed by whatever holds them - the block system nests three of these around every block
    public function transparentWrapperClasses(): array
    {
        $wrappers = [];

        foreach ($this->rules as $rule) {
            if ('contents' !== ($rule['declarations']['display'] ?? '')) {
                continue;
            }

            foreach ($rule['selectors'] as $selector) {
                if (preg_match('/^\.([a-z0-9_-]+)$/i', $selector, $matches)) {
                    $wrappers[$matches[1]] = true;
                }
            }
        }

        return $wrappers;
    }

    // Wins the cascade over the other: stronger, or just as strong but written later
    public static function overrules(array $rule, array $other): bool
    {
        $comparison = $rule['specificity'] <=> $other['specificity'];

        return $comparison > 0 || (0 === $comparison && $rule['order'] > $other['order']);
    }

    /**
     * Whether any selector of the list can land on an element carrying this class.
     *
     * @param string|list<string> $class every class the element carries, a variant being named by its base too (".hero.hero--has-bg")
     * @param list<string>        $tags  the tags it is used on, "*" standing for "any", the element being built by a template expression
     */
    public function canMatch(array $selectors, string | array $class, array $tags, array $containers = [], array $wrappers = []): bool
    {
        foreach ($selectors as $selector) {
            // As a flex or grid item the element is placed by its container, not by its own margins, so writing them there is a layout decision rather than an accident
            if ([] !== $containers && $this->isInsideLayoutContainer($selector, $containers)) {
                continue;
            }

            if ([] !== $wrappers && $this->reachesOnlyByTagUnderAConcreteAncestor($selector, $wrappers)) {
                continue;
            }

            if ($this->compoundMatches(self::subjectCompound($selector), $class, $tags)) {
                return true;
            }
        }

        return false;
    }

    // A rule whose subject names no class of its own only ever reaches a component through its tag. Scoped under a concrete styled ancestor, that is a coincidence of tag names rather than a collision - a card is not going to turn up inside ".menu-site-tagline" whatever the fact that both use a <div>. Scoped under nothing, or under wrappers that are no box at all, it is the opposite: those hold every block a page carries, which is exactly the shape of the reset that flattened the slider.
    private function reachesOnlyByTagUnderAConcreteAncestor(string $selector, array $wrappers): bool
    {
        $compounds = self::splitOnCombinators($selector)['compounds'];
        $subject = array_pop($compounds);

        foreach (self::splitCompound((string) $subject) as $token) {
            if (str_starts_with($token, '.')) {
                return false;
            }
        }

        foreach ($compounds as $compound) {
            foreach (self::splitCompound($compound) as $token) {
                if (str_starts_with($token, '.') && !isset($wrappers[substr($token, 1)])) {
                    return true;
                }
            }
        }

        return false;
    }

    // Comparable as an array: ids, then classes/attributes/pseudo-classes, then types
    public static function specificity(string $selector): array
    {
        $ids = 0;
        $classes = 0;
        $types = 0;

        // Split the way the combinator reader does, parentheses kept whole: an "explode" on the space would cut ":is(.blocks, .block-animation)" in two and count each half as a compound of its own
        foreach (self::splitSelectorList($selector) as $part) {
            foreach (self::splitOnCombinators($part)['compounds'] as $compound) {
                foreach (self::splitCompound(trim($compound)) as $token) {
                    // ":is()"/":not()"/":has()" count as their strongest argument, ":where()" as nothing at all
                    if (preg_match('/^:(is|not|has|matches)\((.*)\)$/i', $token, $matches)) {
                        $nested = array_map(self::specificity(...), self::splitSelectorList($matches[2]));
                        [$nestedIds, $nestedClasses, $nestedTypes] = [] === $nested ? [0, 0, 0] : max($nested);
                        $ids += $nestedIds;
                        $classes += $nestedClasses;
                        $types += $nestedTypes;

                        continue;
                    }

                    if (preg_match('/^:where\(/i', $token) || '*' === $token) {
                        continue;
                    }

                    if (str_starts_with($token, '#')) {
                        ++$ids;
                    } elseif (str_starts_with($token, '::')) {
                        ++$types;
                    } elseif (str_starts_with($token, '.') || str_starts_with($token, '[') || str_starts_with($token, ':')) {
                        ++$classes;
                    } elseif ('' !== $token) {
                        ++$types;
                    }
                }
            }
        }

        return [$ids, $classes, $types];
    }

    // Splits on top-level commas only, a ":is(a, b)" holding its own
    public static function splitSelectorList(string $list): array
    {
        $selectors = [];
        $current = '';
        $depth = 0;

        foreach (str_split($list) as $character) {
            if ('(' === $character || '[' === $character) {
                ++$depth;
            } elseif (')' === $character || ']' === $character) {
                --$depth;
            } elseif (0 === $depth && ',' === $character) {
                $selectors[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $character;
        }

        if ('' !== trim($current)) {
            $selectors[] = trim($current);
        }

        return $selectors;
    }

    // Splits a selector into its compounds and the combinators between them, parentheses and brackets kept whole
    public static function splitOnCombinators(string $selector): array
    {
        $compounds = [];
        $combinators = [];
        $current = '';
        $combinator = null;
        $depth = 0;

        foreach (str_split(trim($selector)) as $character) {
            if ('(' === $character || '[' === $character) {
                ++$depth;
            } elseif (')' === $character || ']' === $character) {
                --$depth;
            } elseif (0 === $depth && in_array($character, [' ', '>', '+', '~'], true)) {
                if ('' !== $current) {
                    $compounds[] = $current;
                    $current = '';
                    $combinator = ' ';
                }

                // A ">" surrounded by spaces is one combinator, not three
                if (' ' !== $character) {
                    $combinator = $character;
                }

                continue;
            }

            if ('' === $current && null !== $combinator) {
                $combinators[] = $combinator;
                $combinator = null;
            }

            $current .= $character;
        }

        if ('' !== $current) {
            $compounds[] = $current;
        }

        return ['compounds' => $compounds, 'combinators' => $combinators];
    }

    // The rightmost compound is the one the rule actually styles; everything left of it is context we cannot resolve without a DOM
    public static function subjectCompound(string $selector): string
    {
        $compounds = self::splitOnCombinators($selector)['compounds'];

        return [] === $compounds ? '' : (string) end($compounds);
    }

    // Splits a compound into its type/class/id/attribute/pseudo parts, keeping any parenthesised argument whole
    public static function splitCompound(string $compound): array
    {
        preg_match_all('/(?:[.#:]{1,2}[a-z0-9_-]+(?:\((?:[^()]|\([^()]*\))*\))?|\[[^\]]*\]|\*|[a-z][a-z0-9]*)/i', $compound, $matches);

        return $matches[0];
    }

    // Any ancestor naming a flex/grid container is enough: the rule is sizing the element as an item of that layout, whatever the depth it sits at
    private function isInsideLayoutContainer(string $selector, array $containers): bool
    {
        $compounds = self::splitOnCombinators($selector)['compounds'];
        array_pop($compounds);

        foreach ($compounds as $compound) {
            // Only a class written plainly: what a ":is()" holds is a list of alternatives, and one of them being a container says nothing about the others
            foreach (self::splitCompound($compound) as $token) {
                if (str_starts_with($token, '.') && isset($containers[substr($token, 1)])) {
                    return true;
                }
            }
        }

        return false;
    }

    // Undecidable parts are read as "could match": a missed collision is the failure that costs, a reported one only costs a look
    private function compoundMatches(string $compound, string | array $class, array $tags): bool
    {
        // A pseudo-element styles a generated box, never the element itself, so its margin can't move the component
        if (str_contains($compound, '::')) {
            return false;
        }

        return array_all(self::splitCompound($compound), fn (string $part): bool => $this->partMatches($part, $class, $tags));
    }

    // One piece of a compound weighed on its own: a functional pseudo-class resolves against what it wraps, a class or a tag against what the component carries, and anything unresolvable rules nothing out
    private function partMatches(string $part, string | array $class, array $tags): bool
    {
        if (preg_match('/^:(is|where|matches)\((.*)\)$/i', $part, $matches)) {
            return $this->canMatch(self::splitSelectorList($matches[2]), $class, $tags);
        }

        // The exclusion a fixed collision leaves behind, and the whole point of checking: ":not(.slider)" is what makes the reset safe
        if (preg_match('/^:not\((.*)\)$/i', $part, $matches)) {
            return !$this->canMatch(self::splitSelectorList($matches[1]), $class, $tags);
        }

        // Any other pseudo-class is a state or a position we can't resolve, so it doesn't rule the match out
        if (str_starts_with($part, ':') || str_starts_with($part, '[')) {
            return true;
        }

        if (str_starts_with($part, '#')) {
            return false;
        }

        if (str_starts_with($part, '.')) {
            return in_array(substr($part, 1), (array) $class, true);
        }

        return '*' === $part || in_array('*', $tags, true) || in_array(strtolower($part), $tags, true);
    }

    // Every rule of the unnested cascade, in source order, with its selectors, declarations and specificity
    private static function parse(string $css): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        $rules = [];
        $buffer = '';
        $depth = 0;
        $skipUntilDepth = null;

        foreach (str_split($css) as $character) {
            if ('{' === $character) {
                ++$depth;
                self::openBlock(trim($buffer), $depth, $rules, $skipUntilDepth);
                $buffer = '';

                continue;
            }

            if ('}' === $character) {
                if (null === $skipUntilDepth && 1 === $depth && [] !== $rules && !isset($rules[count($rules) - 1]['declarations'])) {
                    self::closeRule($rules[count($rules) - 1], $buffer);
                }

                if (null !== $skipUntilDepth && $depth === $skipUntilDepth) {
                    $skipUntilDepth = null;
                }

                --$depth;
                $buffer = '';

                continue;
            }

            $buffer .= $character;
        }

        // A nested at-rule leaves its opening prelude behind as a rule with no body
        return array_values(array_filter($rules, static fn (array $rule): bool => isset($rule['declarations'])));
    }

    // Last one wins, as the browser does when a rule declares the same property twice
    // An opening brace: a top-level rule opens its own entry, an at-rule this reading skips arms the skipping, and anything nested is passed over
    private static function openBlock(string $prelude, int $depth, array &$rules, ?int &$skipUntilDepth): void
    {
        if (1 === $depth && str_starts_with($prelude, '@')) {
            $skipUntilDepth = self::isSkippedAtRule($prelude) ? $depth : $skipUntilDepth;

            return;
        }

        if (null === $skipUntilDepth && 1 === $depth) {
            $rules[] = ['prelude' => $prelude];
        }
    }

    // An at-rule this reading has nothing to say about, whose whole block is stepped over
    private static function isSkippedAtRule(string $prelude): bool
    {
        preg_match('/^@([a-z-]+)/i', $prelude, $matches);

        return in_array(strtolower($matches[1] ?? ''), self::SKIPPED_AT_RULES, true);
    }

    // The rule read out once its closing brace is reached: what it selects, what it declares, and how strongly
    private static function closeRule(array &$rule, string $declarations): void
    {
        // Whitespace collapsed first: sass writes a long selector list across several lines, and a newline inside an ":is()" hides it from every reading below
        $prelude = (string) preg_replace('/\s+/', ' ', $rule['prelude']);

        $rule['selectors'] = self::splitSelectorList($prelude);
        $rule['declarations'] = self::parseDeclarations($declarations);
        $rule['specificity'] = max(array_map(self::specificity(...), $rule['selectors']));
    }

    private static function parseDeclarations(string $body): array
    {
        $declarations = [];

        foreach (explode(';', $body) as $declaration) {
            $parts = explode(':', $declaration, 2);

            if (2 === count($parts) && '' !== trim($parts[1])) {
                $declarations[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        return $declarations;
    }
}
