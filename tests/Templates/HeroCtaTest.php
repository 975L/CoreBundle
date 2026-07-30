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
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// Both calls to action are optional, so the four combinations are rendered rather than read as text
class HeroCtaTest extends TestCase
{
    public function testBothSidesGivenRenderBothButtonsInsideTheRow(): void
    {
        $html = $this->render([
            'primaryLabel' => 'Devis', 'primaryUrl' => '/devis',
            'secondaryLabel' => 'Tarifs', 'secondaryUrl' => '/tarifs',
        ]);

        $this->assertStringContainsString('class="hero__cta"', $html);
        $this->assertStringContainsString('<a class="section-btn section-btn--primary" href="/devis">Devis</a>', $html);
        $this->assertStringContainsString('<a class="section-btn section-btn--ghost" href="/tarifs">Tarifs</a>', $html);
    }

    // The secondary one alone is a legitimate composition too - the row stays, holding just it
    public function testOnlyTheSecondaryOneGivenRendersItAloneInTheRow(): void
    {
        $html = $this->render(['secondaryLabel' => 'Tarifs', 'secondaryUrl' => '/tarifs']);

        $this->assertStringContainsString('class="hero__cta"', $html);
        $this->assertStringNotContainsString('section-btn--primary', $html);
        $this->assertStringContainsString('section-btn--ghost', $html);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function halfFilledProvider(): array
    {
        return [
            'primary label with no url' => [['primaryLabel' => 'Devis']],
            'primary url with no label' => [['primaryUrl' => '/devis']],
            'secondary label with no url' => [['secondaryLabel' => 'Tarifs']],
            'secondary url with no label' => [['secondaryUrl' => '/tarifs']],
        ];
    }

    // A button needs both its sides: half of one renders nothing at all, row included
    #[\PHPUnit\Framework\Attributes\DataProvider('halfFilledProvider')]
    public function testAButtonMissingOneOfItsTwoSidesIsDropped(array $cta): void
    {
        $html = $this->render($cta);

        $this->assertStringNotContainsString('hero__cta', $html);
        $this->assertStringNotContainsString('section-btn', $html);
    }

    // No call to action at all: the row itself is gone, not just emptied
    public function testNoCallToActionAtAllDropsTheWholeRow(): void
    {
        $html = $this->render([]);

        $this->assertStringNotContainsString('hero__cta', $html);
        $this->assertStringContainsString('class="hero__title"', $html);
    }

    // Twig resolves these at compile time, so they must exist even when never reached
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addFilter(new TwigFilter('trix_inline', static fn (?string $value): string => (string) $value, ['is_safe' => ['html']]));
        $twig->addFilter(new TwigFilter('to_bool', static fn (mixed $value): bool => (bool) $value));
        $twig->addFunction(new TwigFunction('vich_uploader_asset', static fn (mixed $media): string => ''));

        return $twig->render('components/Hero/Hero.html.twig', $context + ['title' => 'Un titre']);
    }
}
