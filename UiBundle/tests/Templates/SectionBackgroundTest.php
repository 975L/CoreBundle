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

// Locks the three guarantees the "background" option rests on, variants, adapters and fallbacks
class SectionBackgroundTest extends TestCase
{
    // The kinds offering the option: component template => block adapter
    private const array KINDS = [
        'components/Hero/Hero.html.twig' => 'blocks/Hero.html.twig',
        'components/Feature/Bar.html.twig' => 'blocks/FeatureBar.html.twig',
        'components/Text/Section.html.twig' => 'blocks/TextSection.html.twig',
    ];

    private const array VARIANTS = ['muted', 'primary', 'dark'];

    // The value comes from stored block data, so every component must match it against the known variants
    public function testEveryComponentMatchesTheValueAgainstTheKnownVariantsBeforeBuildingTheClass(): void
    {
        foreach (array_keys(self::KINDS) as $component) {
            // Comments document the mechanism and name the classes, which is not markup
            $twig = (string) preg_replace('/\{#.*?#\}/s', '', $this->template($component));

            $this->assertMatchesRegularExpression(
                "/background\|default\('?'?\) in \['muted', 'primary', 'dark'\]/",
                $twig,
                sprintf('"%s" must whitelist the background value before turning it into a class.', $component)
            );
            // One single place builds the class, right after that check - no other occurrence of the prefix
            $this->assertSame(
                1,
                substr_count($twig, 'section--bg-'),
                sprintf('"%s" writes the section--bg- prefix more than once, only the whitelisted set may build it.', $component)
            );
        }
    }

    // A component supporting the option is useless if the block adapter never passes the stored value on
    public function testEveryBlockAdapterPassesTheStoredBackgroundToItsComponent(): void
    {
        foreach (self::KINDS as $component => $adapter) {
            $this->assertStringContainsString(
                'background="{{ background|default(\'\') }}"',
                $this->template($adapter),
                sprintf('"%s" must pass the block\'s own background to %s.', $adapter, $component)
            );
        }
    }

    // Every rule reading a variant property must state its own neutral fallback, or a plain section breaks
    public function testEveryRuleReadingASectionPropertyStatesItsNeutralFallback(): void
    {
        $checked = 0;

        foreach ($this->declarationsBySelector() as [$selector, $declaration]) {
            // Inside a variant's own block the properties are being defined, not consumed
            if (str_contains($selector, 'section--bg-') || str_contains($selector, 'hero--has-bg')) {
                continue;
            }

            preg_match_all('/var\(\s*(--section-[a-z-]+)\s*([,)])/', $declaration, $matches, PREG_SET_ORDER);

            foreach ($matches as [, $property, $next]) {
                ++$checked;
                $this->assertSame(
                    ',',
                    $next,
                    sprintf('"%s" reads %s with no fallback in "%s" - a section with no variant would render with no value at all.', $selector, $property, trim($declaration))
                );
            }
        }

        $this->assertGreaterThan(0, $checked, 'No section property read at all, the test itself is broken.');
    }

    // Each variant must define every property read above, else a flat inverts only part of what it holds
    public function testEveryColoredVariantDefinesTheWholeSetOfProperties(): void
    {
        $scss = $this->scss();

        foreach (['primary', 'dark'] as $variant) {
            foreach (['--section-background', '--section-text', '--section-text-soft', '--section-accent', '--section-border', '--section-overlay'] as $property) {
                $this->assertMatchesRegularExpression(
                    '/\.section--bg-' . $variant . '[^{]*\{[^}]*' . $property . ':/s',
                    $scss,
                    sprintf('The "%s" variant defines no %s.', $variant, $property)
                );
            }
        }
    }

    // The three variants of the CSS, the form field and the components must stay the same three
    public function testTheFormFieldOffersExactlyTheVariantsTheStylesheetDefines(): void
    {
        $trait = (string) file_get_contents(\dirname(__DIR__, 2) . '/src/Form/Block/HasBackgroundFieldTrait.php');
        $scss = $this->scss();

        foreach (self::VARIANTS as $variant) {
            $this->assertStringContainsString("=> '" . $variant . "'", $trait, sprintf('The form field offers no "%s" choice.', $variant));
            $this->assertStringContainsString('.section--bg-' . $variant, $scss, sprintf('The stylesheet defines no "%s" variant.', $variant));
        }

        preg_match_all('/\.section--bg-([a-z]+)/', $scss, $matches);

        $this->assertSame(self::VARIANTS, array_values(array_unique($matches[1])), 'The stylesheet knows a variant the form field does not offer, or the other way round.');
    }

    // Walks the stylesheet, pairing each declaration with the selector it belongs to
    // @return iterable<array{0: string, 1: string}>
    private function declarationsBySelector(): iterable
    {
        $selector = '';
        $pending = '';

        foreach (explode("\n", $this->scss()) as $line) {
            $line = trim($line);

            if (str_ends_with($line, '{')) {
                // A selector list spans as many lines as it has entries, each but the last ending with a comma
                $selector = trim($pending . ' ' . rtrim($line, '{'));
                $pending = '';

                continue;
            }

            if (str_ends_with($line, ',')) {
                $pending .= ' ' . $line;

                continue;
            }

            $pending = '';

            if (str_contains($line, ':') && !str_starts_with($line, '//') && !str_starts_with($line, '/*')) {
                yield [$selector, $line];
            }
        }
    }

    private function scss(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/sass/_page-sections.scss');
    }

    private function template(string $path): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/' . $path);
    }
}
