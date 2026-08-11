<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// Replaces every var() in a stylesheet by the value it resolves to, then drops the :root blocks that fed it. Meant for emails: a CSS inliner copies declarations into style="" attributes verbatim, so a "background-color: var(--primary)" reaches the recipient as-is - and Gmail, Outlook and most mobile clients resolve no custom property, dropping the declaration entirely. Colors simply did not apply there.
//
// Done here rather than at sass compile time on purpose: --primary reads "var(--c975l-color-primary, var(--button-background-primary))", and --c975l-color-primary is written at runtime by the app's own theme config (ThemeVariablesCssListener). A literal baked by sass would freeze every site on the bundle's default palette instead of the one its admin picked.
class CssVariableResolver
{
    // A var() chain deeper than this is a cycle the CSS itself would treat as invalid
    private const int MAX_DEPTH = 32;

    public function resolve(string $css): string
    {
        $tokens = $this->declaredTokens($css);

        $resolved = [];
        foreach (array_keys($tokens) as $name) {
            $resolved[$name] = $this->resolveValue($tokens[$name], $tokens, 0);
        }

        return $this->stripRootBlocks($this->evaluateColorMix($this->substitute($css, $resolved, true)));
    }

    // Computes the "color-mix(in srgb, <color> <p>%, <color>)" a resolved token can still hold - the footer's own border and link hover are mixed out of its background. Resolving the var() inside is not enough: color-mix is no better supported than a custom property in a mail client, so the declaration would be dropped all the same. Only the srgb form is evaluated, the only one these stylesheets use; anything else is left untouched rather than approximated in the wrong color space.
    private function evaluateColorMix(string $css): string
    {
        // Each color is either a function call or a bare token: an "rgb(11, 55, 178)" holds commas of its own, which a plain "everything up to the comma" capture cuts in half
        $color = '(rgba?\([^()]*\)|[^,()\s]+)';
        $pattern = '/color-mix\(\s*in\s+srgb\s*,\s*' . $color . '\s+([\d.]+)%\s*,\s*' . $color . '\s*\)/i';

        for ($pass = 0; $pass < self::MAX_DEPTH; ++$pass) {
            $replaced = preg_replace_callback($pattern, function (array $m): string {
                $first = $this->parseColor(trim($m[1]));
                $second = $this->parseColor(trim($m[3]));

                if (null === $first || null === $second) {
                    return $m[0];
                }

                $weight = (float) $m[2] / 100;
                $mixed = [];
                foreach ([0, 1, 2] as $channel) {
                    $mixed[] = (int) round($first[$channel] * $weight + $second[$channel] * (1 - $weight));
                }

                return sprintf('rgb(%d, %d, %d)', ...$mixed);
            }, $css);

            if ($replaced === $css) {
                break;
            }

            $css = (string) $replaced;
        }

        return $css;
    }

    /** @return array{int, int, int}|null */
    private function parseColor(string $color): ?array
    {
        $named = ['black' => [0, 0, 0], 'white' => [255, 255, 255], 'grey' => [128, 128, 128], 'gray' => [128, 128, 128]];
        $color = strtolower(trim($color));

        if (isset($named[$color])) {
            return $named[$color];
        }

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color, $hex)) {
            $value = $hex[1];
            if (3 === \strlen($value)) {
                $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
            }

            return [(int) hexdec(substr($value, 0, 2)), (int) hexdec(substr($value, 2, 2)), (int) hexdec(substr($value, 4, 2))];
        }

        if (preg_match('/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)/', $color, $rgb)) {
            return [(int) round((float) $rgb[1]), (int) round((float) $rgb[2]), (int) round((float) $rgb[3])];
        }

        return null;
    }

    /**
     * Every custom property declared in a :root block, later declarations winning - which is the order the
     * layout concatenates them in: the bundle defaults first, then the site's, then the admin's own.
     *
     * @return array<string, string>
     */
    private function declaredTokens(string $css): array
    {
        preg_match_all('/:root[^{}]*\{([^{}]*)\}/', $css, $blocks);

        $tokens = [];
        foreach ($blocks[1] as $block) {
            preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+)/i', $block, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $tokens[$match[1]] = trim($match[2]);
            }
        }

        return $tokens;
    }

    /**
     * @param array<string, string> $tokens
     */
    private function resolveValue(string $value, array $tokens, int $depth): string
    {
        if ($depth >= self::MAX_DEPTH) {
            return $value;
        }

        $substituted = $this->substitute($value, $tokens, false);

        // A value can resolve through several hops (--primary -> --c975l-color-primary -> a literal)
        return $substituted === $value ? $value : $this->resolveValue($substituted, $tokens, $depth + 1);
    }

    /**
     * Replaces each var(--name[, fallback]) by the token's value, or by the fallback when it is undeclared.
     * Scanned rather than matched by regex: a fallback can itself hold parentheses, which no regex closes
     * correctly - "var(--x, color-mix(in srgb, var(--y) 50%, white))" being the case that breaks them.
     *
     * @param array<string, string> $tokens
     */
    private function substitute(string $css, array $tokens, bool $isStylesheet): string
    {
        $out = '';
        $offset = 0;

        while (false !== $start = strpos($css, 'var(', $offset)) {
            $out .= substr($css, $offset, $start - $offset);
            $end = $this->matchingParenthesis($css, $start + 3);

            if (null === $end) {
                return $out . substr($css, $start);
            }

            $inner = substr($css, $start + 4, $end - $start - 4);
            $comma = $this->topLevelComma($inner);
            $name = trim(null === $comma ? $inner : substr($inner, 0, $comma));
            $fallback = null === $comma ? null : trim(substr($inner, $comma + 1));

            if (isset($tokens[$name])) {
                $out .= $isStylesheet ? $this->resolveValue($tokens[$name], $tokens, 0) : $tokens[$name];
            } elseif (null !== $fallback) {
                $out .= $fallback;
            } else {
                // Undeclared and no fallback: the declaration is invalid anyway, so the var() is left in place rather than replaced by something that would silently paint the wrong thing
                $out .= substr($css, $start, $end - $start + 1);
            }

            $offset = $end + 1;
        }

        return $out . substr($css, $offset);
    }

    // Index of the ")" closing the "(" at $open, nesting included
    private function matchingParenthesis(string $css, int $open): ?int
    {
        $depth = 0;
        $length = \strlen($css);

        for ($i = $open; $i < $length; ++$i) {
            if ('(' === $css[$i]) {
                ++$depth;
            } elseif (')' === $css[$i]) {
                --$depth;
                if (0 === $depth) {
                    return $i;
                }
            }
        }

        return null;
    }

    // Offset of the comma separating the token name from its fallback, ignoring the ones nested in a function
    private function topLevelComma(string $inner): ?int
    {
        $depth = 0;
        $length = \strlen($inner);

        for ($i = 0; $i < $length; ++$i) {
            if ('(' === $inner[$i]) {
                ++$depth;
            } elseif (')' === $inner[$i]) {
                --$depth;
            } elseif (',' === $inner[$i] && 0 === $depth) {
                return $i;
            }
        }

        return null;
    }

    // Once every var() is resolved the :root blocks carry nothing a recipient can use, and an inliner has no element to attach them to anyway. The @layer wrapping them goes too, being both empty afterwards and unknown to most mail clients
    private function stripRootBlocks(string $css): string
    {
        $css = (string) preg_replace('/:root[^{}]*\{[^{}]*\}/', '', $css);
        $css = (string) preg_replace('/@layer[^{}]*\{\s*\}/', '', $css);

        return trim((string) preg_replace('/\n{3,}/', "\n\n", $css));
    }
}
