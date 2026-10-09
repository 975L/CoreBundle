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
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

// A card's button is a link a search engine reads by its text: left to its default, it names the destination rather than saying a bare "learn more"
class CardButtonLabelTest extends TestCase
{
    // The collection item drawn by the default card, the variant a "collection_entry" block renders
    public function testACollectionItemButtonNamesItsDestination(): void
    {
        $html = $this->render('blocks/CollectionItem.html.twig', ['title' => 'Autotech automobile', 'url' => '/pages/autotech-automobile', 'imageUrl' => '/uploads/autotech.webp']);

        $this->assertStringContainsString('label="En savoir plus sur Autotech automobile"', $html);
    }

    // The portrait variant writes its own markup, button included
    public function testAPortraitButtonNamesItsDestination(): void
    {
        $html = $this->render('blocks/CollectionItem.html.twig', ['title' => 'Mamie ViteVite', 'url' => '/pages/mamie-vitevite', 'variant' => 'portrait']);

        $this->assertStringContainsString('label="En savoir plus sur Mamie ViteVite"', $html);
    }

    // The card block the editor composes takes the same default
    public function testACardBlockButtonNamesItsDestination(): void
    {
        $html = $this->render('blocks/Card.html.twig', ['title' => 'Nos tarifs', 'url' => '/pages/tarifs', 'block' => (object) ['media' => []]]);

        $this->assertStringContainsString('label="En savoir plus sur Nos tarifs"', $html);
    }

    // A label the editor wrote is theirs, and a card with no title keeps the generic one
    public function testAWrittenLabelWinsAndAnUntitledCardKeepsTheGenericOne(): void
    {
        $this->assertStringContainsString('label="Voir le site"', $this->render('blocks/CollectionItem.html.twig', ['title' => 'Autotech', 'url' => '/pages/autotech', 'imageUrl' => '/a.webp', 'buttonLabel' => 'Voir le site']));
        $this->assertStringContainsString('label="En savoir plus"', $this->render('blocks/CollectionItem.html.twig', ['title' => '', 'url' => '/pages/autotech', 'imageUrl' => '/a.webp']));
    }

    // A bare Environment writes the "<twig:...>" calls out as text, which is what these assertions read
    private function render(string $template, array $context): string
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['label.learn_more' => 'En savoir plus', 'label.learn_more_about' => 'En savoir plus sur %title%'], 'fr', 'ui');
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        $twig->addExtension(new TranslationExtension($translator));
        // Named by the card block for its attached media, which these cards do not carry
        $twig->addFunction(new TwigFunction('vich_uploader_asset', static fn (): string => ''));

        return $twig->render($template, $context);
    }
}
