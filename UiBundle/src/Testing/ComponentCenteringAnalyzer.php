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
 * Finds the components a stylesheet centres through "margin: … auto" and the rules strong enough to write
 * that centering away - the defect that left the slider hugging the left edge in v1.12.0, with nothing in
 * its own sass changed to show for it.
 *
 * Run over one bundle's sheet it catches that bundle's own collisions; run over several in load order it
 * catches the ones between them, which is where a page-wide "section { … }" meets a component's own rule.
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
final class ComponentCenteringAnalyzer
{
    // Every property able to overwrite the inline axis a centering declaration lives on
    private const INLINE_MARGIN_PROPERTIES = ['margin', 'margin-inline', 'margin-left', 'margin-right', 'margin-inline-start', 'margin-inline-end'];

    public function __construct(
        private readonly StylesheetCascade $cascade,
    ) {
    }

    /**
     * The class each component writes on a real element, mapped to every tag it is used on - the reset that
     * broke the slider named no class at all, only "section", so the tag is what ties the two together.
     *
     * @param string ...$templateDirectories directories holding the component templates, scanned recursively
     */
    public static function tagsByClass(string ...$templateDirectories): array
    {
        $tags = [];

        foreach ($templateDirectories as $directory) {
            foreach (glob($directory . '/*/*.html.twig') ?: [] as $template) {
                $twig = (string) file_get_contents($template);
                preg_match_all('/<([a-z][a-z0-9]*|\{\{[^}]*\}\})[^>]*?\bclass="([^"{]*)/i', $twig, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

                foreach ($matches as $match) {
                    // Only the literal leading classes: what follows a "{{" is a Twig expression, unknown until rendered
                    foreach (preg_split('/\s+/', trim($match[2][0])) ?: [] as $class) {
                        if ('' === $class) {
                            continue;
                        }

                        foreach (self::tagNames($match[1][0], $twig, (int) $match[1][1]) as $tag) {
                            $tags[$class][$tag] = true;
                        }
                    }
                }
            }
        }

        return array_map('array_keys', $tags);
    }

    // The tags an element is rendered as. Half the sections pick theirs at runtime ("<{{ tag }}"), so the literals of the expression are what names them - the whole of the page-section family is invisible to a reading that only takes a written-out tag.
    private static function tagNames(string $tag, string $twig, int $offset): array
    {
        if (!str_starts_with($tag, '{{')) {
            return [strtolower($tag)];
        }

        $expression = trim($tag, '{} ');

        // A bare variable holds whatever the last "set" above it wrote there, the branches of that expression being the tags it can take
        if (preg_match('/^[a-z0-9_]+$/i', $expression)) {
            preg_match_all('/\{%\s*set\s+' . preg_quote($expression, '/') . '\s*=([^%]*)%\}/i', substr($twig, 0, $offset), $sets);
            $expression = (string) array_pop($sets[1]);
        }

        preg_match_all('/[\'"]([a-z][a-z0-9]*)[\'"]/i', $expression, $literals);

        // Nothing written out: read as "any tag", an unresolved collision costing more than a reported one
        return [] === $literals[1] ? ['*'] : array_map('strtolower', $literals[1]);
    }

    /**
     * @return array{centered: list<string>, breakouts: list<string>, violations: list<array{class: string, centering: string, selectors: string, override: string, source: string, kind: string}>}
     */
    public function analyse(array $tagsByClass): array
    {
        $rules = $this->cascade->rules();
        $containers = $this->cascade->layoutContainerClasses();
        $wrappers = $this->cascade->transparentWrapperClasses();
        $centered = [];
        $violations = [];

        foreach ($tagsByClass as $class => $tags) {
            $centering = $this->lastCenteringRule($rules, (string) $class);

            if (null === $centering) {
                continue;
            }

            $centered[] = (string) $class;

            foreach ($rules as $rule) {
                if ($rule['order'] === $centering['order'] || !StylesheetCascade::overrules($rule, $centering)) {
                    continue;
                }

                $override = $this->inlineMarginOverride($rule);

                if (null === $override || !$this->cascade->canMatch($rule['selectors'], (string) $class, $tags, $containers, $wrappers)) {
                    continue;
                }

                $violations[] = [
                    'class' => (string) $class,
                    'centering' => $centering['declaration'],
                    'selectors' => implode(', ', $rule['selectors']),
                    'override' => $override,
                    'source' => $rule['source'],
                    'kind' => 'centered by',
                ];
            }
        }

        $breakouts = [];

        foreach ($this->breakoutRules($rules, $tagsByClass) as $breakout) {
            $breakouts[] = implode('', array_map(static fn (string $class): string => '.' . $class, $breakout['classes']));

            foreach ($rules as $rule) {
                if ($rule['order'] === $breakout['order'] || !StylesheetCascade::overrules($rule, $breakout)) {
                    continue;
                }

                $override = $this->inlineMarginOverride($rule);

                if (null === $override || !$this->cascade->canMatch($rule['selectors'], $breakout['matches'], $breakout['tags'], $containers, $wrappers)) {
                    continue;
                }

                $violations[] = [
                    'class' => implode('.', $breakout['classes']),
                    'centering' => $breakout['declaration'],
                    'selectors' => implode(', ', $rule['selectors']),
                    'override' => $override,
                    'source' => $rule['source'],
                    'kind' => 'broken out of the measure by',
                ];
            }
        }

        return ['centered' => $centered, 'breakouts' => $breakouts, 'violations' => $violations];
    }

    // Names the two sheets involved, a collision between bundles being unreadable without them
    public static function describe(array $violation): string
    {
        return sprintf(
            "\"%s\" is %s \"%s\" but \"%s\" writes \"%s\" over it (%s).\n"
                . "That rule is stronger and reaches the same element, so the component hugs the left edge.\n"
                . 'Keep it off the inline axis ("margin-block"), or narrow its selector.',
            '.' . $violation['class'],
            $violation['kind'] ?? 'centered by',
            $violation['centering'],
            $violation['selectors'],
            $violation['override'],
            $violation['source']
        );
    }

    // The rules laying a component out past what holds it, through a negative inline margin - the full-bleed flats and the hero with a background. Read off the cascade, not off the templates: those classes are written by a Twig expression, so nothing in the markup names them literally. Only a selector made of classes alone: a contextual one is somebody's deliberate cancelling of the breakout, which is how a flat used as a column slot paints its column instead of the page.
    private function breakoutRules(array $rules, array $tagsByClass): array
    {
        $breakouts = [];

        foreach ($rules as $rule) {
            foreach ($rule['selectors'] as $selector) {
                if ([] === $classes = self::classOnlyCompound($selector)) {
                    continue;
                }

                foreach (self::INLINE_MARGIN_PROPERTIES as $property) {
                    $value = $rule['declarations'][$property] ?? null;

                    if (null === $value || [] === array_filter($this->inlineValues($property, $value), $this->isBreakout(...))) {
                        continue;
                    }

                    $siblings = $this->classesStyledWith($classes, $rules);

                    $breakouts[$selector] = $rule + [
                        'classes' => $classes,
                        // The element carries those too, so a rule naming one of them reaches the breakout: ".text-section" is where a flat is written
                        'matches' => array_values(array_unique(array_merge($classes, $siblings))),
                        'tags' => $this->breakoutTags($classes, $siblings, $tagsByClass),
                        'declaration' => $property . ': ' . $value,
                    ];
                }
            }
        }

        return array_values($breakouts);
    }

    // The classes of a selector made of nothing else, [] for any other shape
    private static function classOnlyCompound(string $selector): array
    {
        return preg_match('/^(?:\.[a-z0-9_-]+)+$/i', $selector) ? array_slice(explode('.', $selector), 1) : [];
    }

    // A negative margin measured against the viewport or what holds the box, as opposed to a pixel nudge placing a handle or hiding a label
    private function isBreakout(string $value): bool
    {
        return 1 === preg_match('/(?:^|[\s,(])-\.?\d[\d.]*(?:vw|vi|%)/', $value);
    }

    // Every class written on the same element, read off the compounds the sheet styles the two together in
    private function classesStyledWith(array $classes, array $rules): array
    {
        $siblings = [];

        foreach ($rules as $rule) {
            foreach ($rule['selectors'] as $selector) {
                if ([] === array_intersect($classes, $compound = self::classOnlyCompound($selector))) {
                    continue;
                }

                foreach (array_diff($compound, $classes) as $sibling) {
                    $siblings[$sibling] = true;
                }
            }
        }

        return array_keys($siblings);
    }

    // The tags a breakout is used on, taken from the classes it is written next to when the markup builds its own name in an expression (" section--bg-" ~ background)
    private function breakoutTags(array $classes, array $siblings, array $tagsByClass): array
    {
        foreach ([$classes, $siblings] as $names) {
            $tags = [];

            foreach ($names as $name) {
                foreach ($tagsByClass[$name] ?? [] as $tag) {
                    $tags[$tag] = true;
                }
            }

            if ([] !== $tags) {
                return array_keys($tags);
            }
        }

        // Nothing ties it to any markup: read as "any tag", an unresolved collision costing more than a reported one
        return ['*'];
    }

    // The last rule declaring the centering, later ones on the same selector being the ones that win
    private function lastCenteringRule(array $rules, string $class): ?array
    {
        $found = null;
        $own = [];

        foreach ($rules as $rule) {
            // The component's own rule, not a contextual one: ".slider" centers itself, ".blocks .slider" is somebody else's opinion
            if (!in_array('.' . $class, $rule['selectors'], true)) {
                continue;
            }

            $own = array_merge($own, $rule['declarations']);

            foreach (self::INLINE_MARGIN_PROPERTIES as $property) {
                $value = $rule['declarations'][$property] ?? null;

                if (null !== $value && in_array('auto', $this->inlineValues($property, $value), true)) {
                    $found = $rule + ['declaration' => $property . ': ' . $value];
                }
            }
        }

        return null !== $found && $this->isCenterable($own) ? $found : null;
    }

    // An "auto" margin only centers a block-level box narrower than what holds it - on anything else it computes to zero and there is nothing to protect (".btn" declares neither, so its own "margin: 1em auto" never centered a thing)
    private function isCenterable(array $declarations): bool
    {
        if (str_starts_with($declarations['display'] ?? '', 'inline')) {
            return false;
        }

        foreach (['width', 'max-width'] as $property) {
            $value = $declarations[$property] ?? null;

            if (null !== $value && !in_array($value, ['auto', 'none', '100%'], true)) {
                return true;
            }
        }

        return false;
    }

    // What the rule writes on the inline axis, when that is something other than "auto"
    private function inlineMarginOverride(array $rule): ?string
    {
        foreach (self::INLINE_MARGIN_PROPERTIES as $property) {
            $value = $rule['declarations'][$property] ?? null;

            if (null === $value) {
                continue;
            }

            foreach ($this->inlineValues($property, $value) as $inlineValue) {
                if ('auto' !== $inlineValue) {
                    return $property . ': ' . $value;
                }
            }
        }

        return null;
    }

    // The inline-axis values a margin declaration sets, the shorthand mapping its 1 to 4 values onto the box
    private function inlineValues(string $property, string $value): array
    {
        $values = $this->splitValues($value);

        if ([] === $values) {
            return [];
        }

        return match ($property) {
            'margin' => match (count($values)) {
                1 => [$values[0]],
                2, 3 => [$values[1]],
                default => [$values[1], $values[3]],
            },
            'margin-inline' => 1 === count($values) ? [$values[0]] : [$values[0], $values[1]],
            default => [$values[0]],
        };
    }

    // Paren-aware, so a "calc(50% - (var(--x) / 2))" stays one value instead of splitting on its own spaces
    private function splitValues(string $value): array
    {
        $values = [];
        $current = '';
        $depth = 0;

        foreach (str_split(trim($value)) as $character) {
            if ('(' === $character) {
                ++$depth;
            } elseif (')' === $character) {
                --$depth;
            } elseif (0 === $depth && ' ' === $character) {
                if ('' !== $current) {
                    $values[] = $current;
                    $current = '';
                }

                continue;
            }

            $current .= $character;
        }

        if ('' !== $current) {
            $values[] = $current;
        }

        return $values;
    }
}
