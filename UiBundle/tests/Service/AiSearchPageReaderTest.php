<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\AiSearchPageReader;
use PHPUnit\Framework\TestCase;

// What the site search answers from: the page's own words, never its chrome
class AiSearchPageReaderTest extends TestCase
{
    public function testReadsTheMainTextWithoutTheSiteChrome(): void
    {
        $page = new AiSearchPageReader()->read(
            '<html lang="fr"><head><title>Horaires - Garage</title></head><body>'
            . '<header><p>En-tête</p></header><nav><ul><li>Accueil</li></ul></nav>'
            . '<main><h1>Nos horaires</h1><p>Ouvert du lundi au vendredi.</p>'
            . '<form><p>Formulaire</p></form><div data-ai-search-ignore><p>Caché</p></div><script>var x;</script></main>'
            . '<footer><p>Pied de page</p></footer></body></html>',
            'en',
        );

        $this->assertSame('Horaires - Garage', $page['title']);
        $this->assertSame('fr', $page['locale']);
        $this->assertSame(["Nos horaires\nOuvert du lundi au vendredi."], $page['chunks']);
    }

    public function testAPageAskingNotToBeIndexedIsSkipped(): void
    {
        $this->assertNull(new AiSearchPageReader()->read('<html><head><meta name="robots" content="noindex, follow"></head><body><main><p>Texte</p></main></body></html>', 'fr'));
    }

    public function testAPageSayingNothingIsSkipped(): void
    {
        $this->assertNull(new AiSearchPageReader()->read('<html><body><main><div></div></main></body></html>', 'fr'));
    }

    // A <li> wrapping a <p> is read once, and the locale defaults when the page declares none
    public function testANestedBlockIsReadOnceAndTheLocaleDefaults(): void
    {
        $page = new AiSearchPageReader()->read('<html><body><ul><li><p>Une fois</p></li></ul></body></html>', 'fr');

        $this->assertSame(['Une fois'], $page['chunks']);
        $this->assertSame('fr', $page['locale']);
        $this->assertSame('Une fois', $page['title']);
    }

    public function testLongTextIsCutIntoPassagesOfBoundedLength(): void
    {
        $page = new AiSearchPageReader()->read('<html><body><main><p>' . str_repeat('un ', 300) . '</p><p>' . str_repeat('deux ', 200) . '</p><p>' . str_repeat('trois ', 500) . '</p></main></body></html>', 'fr');

        // 900 and 1,000 characters don't fit together, and 3,000 is cut into three pieces of its own
        $this->assertCount(5, $page['chunks']);
        foreach ($page['chunks'] as $chunk) {
            $this->assertLessThanOrEqual(AiSearchPageReader::CHUNK_LENGTH, mb_strlen($chunk));
        }
    }
}
