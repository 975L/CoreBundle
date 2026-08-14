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
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\Twig\PropsTokenParser;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;

// Whatever a caller writes on a card beyond its declared props reaches the DOM, which is what makes a per-card color ambiance possible at all
class CardAttributesTest extends TestCase
{
    // The reason the component renders "attributes": the tokens are declared on ":root, [data-theme]" (sass/_tokens.scss), so a card carrying data-theme recomputes the whole chain - but only if the attribute is written out
    public function testADataThemeWrittenOnACardReachesTheDom(): void
    {
        $html = $this->render(['title' => 'Title'], ['data-theme' => 'chaos']);

        $this->assertStringContainsString('data-theme="chaos"', $html);
    }

    // Anything else a caller adds travels the same way, an aria attribute as much as a data one
    public function testAnyOtherAttributeTravelsTheSameWay(): void
    {
        $html = $this->render(['title' => 'Title'], ['role' => 'listitem', 'aria-label' => 'Card']);

        $this->assertStringContainsString('role="listitem"', $html);
        $this->assertStringContainsString('aria-label="Card"', $html);
    }

    // The props the card reads are declared, so none of them leaks into the markup as an HTML attribute - which is what a bare "attributes" would have done to title, icon or content
    public function testADeclaredPropIsNotWrittenOutAsAnAttribute(): void
    {
        $html = $this->render(['title' => 'Title', 'content' => 'Text', 'eyebrow' => 'Eyebrow']);

        $this->assertStringNotContainsString('title="Title"', $html);
        $this->assertStringNotContainsString('content=', $html);
        $this->assertStringNotContainsString('eyebrow=', $html);
    }

    /**
     * @param array<string, mixed>       $context
     * @param array<string, string|bool> $attributes
     */
    private function render(array $context, array $attributes = []): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addTokenParser(new PropsTokenParser());
        // What TwigEnvironmentConfigurator does in the application: without it the rendered attributes come back escaped, quotes included
        $twig->getRuntime(EscaperRuntime::class)->addSafeClass(ComponentAttributes::class, ['html']);

        return $twig->render('components/Card/Card.html.twig', $context + [
            'attributes' => new ComponentAttributes($attributes, $twig->getRuntime(EscaperRuntime::class)),
        ]);
    }
}
