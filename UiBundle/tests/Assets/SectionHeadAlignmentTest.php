<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// SiteBundle's typography centers every h1-h6 and paints it --primary, and its blanket "*" rule hands every other element the body font. A block title inheriting any of that is styled by the theme rather than by its own kind: the defect only shows where the box is wider than the text (a section title floated to the middle of the 640px .section-head while the eyebrow right above it stayed left) and hides itself everywhere else. So every title states its own family and alignment - unless a container centers the whole block on purpose, which then has to state that centering itself.
class SectionHeadAlignmentTest extends TestCase
{
    // Laid out flush against their section: they state "start" rather than inheriting the theme's centering
    private const ALIGNED_TITLES = [
        '.section-eyebrow',
        '.section-title',
        '.expertise-banner__title',
        '.section-step__title',
        '.portfolio-grid__project-title',
        '.video-title',
        '.card-header',
        // Not a block title but the same defect: a document heading centered over the column it introduces
        '.legal h2',
    ];

    // Centering a whole block is a design call, and belongs to whatever box holds the block's text
    private const CENTERING_CONTAINERS = [
        '.hero .section-wrap',
        '.cta-band__inner',
        '.banner-title-overlay',
    ];

    // Every block title reads the title family, whatever element it is rendered as and wherever it is rendered
    private const TITLE_FAMILY = [
        '.section-title',
        '.expertise-banner__title',
        '.cta-band__title',
        '.hero__title',
        '.section-step__title',
        '.portfolio-grid__project-title',
        '.video-title',
        '.card-header',
        '.banner-title-text',
        '.slider-title',
        '.contact-details__name',
        '.legal h2',
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'styles.css' => ['styles.css'],
            'styles.min.css' => ['styles.min.css'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEveryTitleStatesItsOwnAlignment(string $file): void
    {
        $css = $this->normalize($file);

        foreach (self::ALIGNED_TITLES as $class) {
            $this->assertStringContainsString(
                'text-align:start',
                $this->declarationsOf($css, $class, $file),
                sprintf('"%s" declares no alignment of its own in "%s": it is centered by the theme\'s own heading rule.', $class, $file)
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheBlocksMeantToBeCenteredSaySoOnTheirOwnContainer(string $file): void
    {
        $css = $this->normalize($file);

        foreach (self::CENTERING_CONTAINERS as $container) {
            $this->assertStringContainsString(
                'text-align:center',
                $this->declarationsOf($css, $container, $file),
                sprintf('"%s" no longer centers its own block in "%s", which its title relies on rather than stating it.', $container, $file)
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEveryTitleReadsTheTitleFamily(string $file): void
    {
        $css = $this->normalize($file);

        foreach (self::TITLE_FAMILY as $class) {
            $this->assertStringContainsString(
                'font-family:var(--font-family-title',
                $this->declarationsOf($css, $class, $file),
                sprintf('"%s" reads no font family of its own in "%s": the theme decides what it is set in.', $class, $file)
            );
        }
    }

    // The body of the rule whose selector list holds $selector, read off the whitespace-stripped sheet
    private function declarationsOf(string $css, string $selector, string $file): string
    {
        $selector = (string) preg_replace('/\s+/', '', $selector);

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (in_array($selector, explode(',', trim($match[1])), true)) {
                return $match[2];
            }
        }

        $this->fail(sprintf('"%s" has no "%s" rule at all.', $file, $selector));
    }

    private function normalize(string $file): string
    {
        $path = dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s+/', '', $css);
    }
}
