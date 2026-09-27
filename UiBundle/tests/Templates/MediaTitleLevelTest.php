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
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

// The rank of a media's title: <h3> for the component, whatever section holds it, <h2> for the block standing straight in the page - where an <h3> right under the page's <h1> skips a level, which is RGAA criterion 9.1 failing
class MediaTitleLevelTest extends TestCase
{
    private const array COMPONENTS = [
        'components/Video/Video.html.twig' => 'video-title',
        'components/Video/Iframe.html.twig' => 'video-iframe-title',
        'components/Audio/Audio.html.twig' => 'audio-title',
    ];

    // Nothing said - a static caller - keeps the <h3> these components have always drawn
    public function testTheComponentsKeepTheirH3WhenNoLevelIsGiven(): void
    {
        foreach (self::COMPONENTS as $template => $class) {
            $this->assertStringContainsString(sprintf('<h3 class="%s">Film</h3>', $class), $this->render($template, []), $template);
        }
    }

    public function testTheComponentsDrawTheirTitleAtTheGivenLevel(): void
    {
        foreach (self::COMPONENTS as $template => $class) {
            $this->assertStringContainsString(sprintf('<h2 class="%s">Film</h2>', $class), $this->render($template, ['level' => 'h2']), $template);
        }
    }

    // Matched against the offered levels, never interpolated: a caller must not be able to write a tag name
    public function testALevelOutsideTheOfferedOnesFallsBackToH3(): void
    {
        foreach (self::COMPONENTS as $template => $class) {
            $this->assertStringContainsString(sprintf('<h3 class="%s">Film</h3>', $class), $this->render($template, ['level' => 'script']), $template);
        }
    }

    // The case this exists for: a media block opening the page, right under its <h1>
    public function testTheBlocksHandTheirComponentAnH2(): void
    {
        foreach (['blocks/Video.html.twig', 'blocks/VideoIframe.html.twig', 'blocks/Audio.html.twig'] as $template) {
            $this->assertMatchesRegularExpression('#<twig:c975LUi:(?:Video|Audio):\w+ [^>]*level="h2"#', (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/' . $template), $template);
        }
    }

    private function render(string $template, array $context): string
    {
        $loader = new FilesystemLoader(\dirname(__DIR__, 2) . '/templates');
        $twig = new Environment($loader);
        // Untranslated keys come back as-is, which is enough for the players' fallback text
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        // The bundle's own filter, which a bare Environment knows nothing of - the same rule BoolExtension applies
        $twig->addFilter(new TwigFilter('to_bool', static fn (mixed $value): bool => !\in_array($value, [false, 'false', '0', 0, ''], true)));

        return $twig->render($template, [...$context, 'src' => '/media/film', 'type' => 'video/mp4', 'title' => 'Film']);
    }
}
