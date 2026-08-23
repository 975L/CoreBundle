<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Contract\PdfGeneratorInterface;
use c975L\UiBundle\Service\DompdfGenerator;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

// The engine this bundle ships, checked on what a caller is promised: bytes that are a PDF, a paper size an object-shaped document can state in millimetres, and a renderer that reaches for nothing outside the site's own files
class DompdfGeneratorTest extends TestCase
{
    public function testItAnswersTheBytesOfARealPdf(): void
    {
        $pdf = $this->generator()->renderHtml('<html lang="fr"><body><p>Bonjour</p></body></html>');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }

    public function testATemplateIsRenderedWithItsOwnVariables(): void
    {
        $pdf = $this->generator(['card.html.twig' => '<html lang="fr"><body><p>{{ amount }}</p></body></html>'])
            ->render('card.html.twig', ['amount' => '50,00 €']);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    // A card is an object of a known size, not a page: 85.6 by 54 millimetres, which the engine is handed in the points a PDF is measured in
    public function testAPaperStatedInMillimetresComesOutAsAPageOfThatSize(): void
    {
        $pdf = $this->generator()->renderHtml('<html lang="fr"><body><p>Carte</p></body></html>', ['paper' => [85.6, 54]]);

        // 85.6 mm and 54 mm at 72 points to the inch, as the page box the file declares
        $this->assertMatchesRegularExpression('/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+242\.\d+\s+153\.\d+/', $pdf);
    }

    // Never enabled: a document is drawn out of markup an admin typed, and an engine allowed to fetch a url would make this server ask for whatever that markup names - the shape of every SSRF there is
    public function testTheEngineFetchesNothingRemoteAndRunsNoCodeFromTheMarkup(): void
    {
        $generator = new \ReflectionMethod(DompdfGenerator::class, 'options');
        $options = $generator->invoke($this->generator(), []);

        $this->assertFalse($options->getIsRemoteEnabled());
        $this->assertFalse($options->getIsPhpEnabled());
    }

    // The one directory a local picture may be read from, so a template naming "../../.env" reads nothing
    public function testALocalPathIsPennedUnderTheSitesPublicDirectory(): void
    {
        $generator = new \ReflectionMethod(DompdfGenerator::class, 'options');
        $options = $generator->invoke($this->generator(), []);

        $this->assertSame(['/srv/site/public/'], $options->getChroot());
    }

    public function testItIsTheContractTheRestOfTheEcosystemAsksFor(): void
    {
        $this->assertInstanceOf(PdfGeneratorInterface::class, $this->generator());
    }

    /** @param array<string, string> $templates */
    private function generator(array $templates = []): DompdfGenerator
    {
        return new DompdfGenerator(new Environment(new ArrayLoader($templates)), '/srv/site');
    }
}
