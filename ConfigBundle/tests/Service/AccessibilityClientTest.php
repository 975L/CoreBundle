<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\AccessibilityClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class AccessibilityClientTest extends TestCase
{
    private function analyze(string $body): array
    {
        $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('<html lang="fr"><head><title>Page</title></head><body><main>' . $body . '</main></body></html>', ['http_code' => 200]));

        return new AccessibilityClient($httpClient)->analyze('https://example.com/pages/home/');
    }

    public function testAPageDeclaringAWellFormedLanguageIsReadAsSuch(): void
    {
        $result = $this->analyze('<p>Bonjour</p>');

        $this->assertSame('fr', $result['language']);
        $this->assertTrue($result['languageIsWellFormed']);
        $this->assertTrue($result['hasMainLandmark']);
    }

    public function testAMissingLanguageIsReportedAsAnEmptyOne(): void
    {
        $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('<html><body><p>Bonjour</p></body></html>', ['http_code' => 200]));

        $result = new AccessibilityClient($httpClient)->analyze('https://example.com/');

        $this->assertSame('', $result['language']);
        $this->assertFalse($result['languageIsWellFormed']);
        $this->assertFalse($result['hasMainLandmark']);
    }

    // The language name spelled out instead of its code - the classic 8.4 offence, and one only a shape check catches
    public function testALanguageWrittenOutInFullIsNotWellFormed(): void
    {
        $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('<html lang="français"><body></body></html>', ['http_code' => 200]));

        $result = new AccessibilityClient($httpClient)->analyze('https://example.com/');

        $this->assertSame('français', $result['language']);
        $this->assertFalse($result['languageIsWellFormed']);
    }

    public function testARegionalLanguageCodeStaysWellFormed(): void
    {
        $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('<html lang="fr-CH"><body></body></html>', ['http_code' => 200]));

        $this->assertTrue(new AccessibilityClient($httpClient)->analyze('https://example.com/')['languageIsWellFormed']);
    }

    public function testAFrameWithoutATitleIsReportedWithItsSource(): void
    {
        $result = $this->analyze('<iframe src="https://www.youtube.com/embed/x"></iframe><iframe src="/ok" title="Une vidéo"></iframe>');

        $this->assertSame(['<iframe src="https://www.youtube.com/embed/x">'], $result['framesWithoutTitle']);
    }

    public function testAFrameWhoseTitleIsBlankCountsAsHavingNone(): void
    {
        $this->assertCount(1, $this->analyze('<iframe src="/x" title="   "></iframe>')['framesWithoutTitle']);
    }

    public function testALinkIsNamedByItsTextItsImageAltItsAriaLabelOrItsSvgTitle(): void
    {
        $result = $this->analyze(
            '<a href="/a">Nous contacter</a>'
            . '<a href="/b"><img src="/logo.png" alt="Accueil"></a>'
            . '<a href="/c" aria-label="Notre page Facebook"><i class="fab"></i></a>'
            . '<a href="/d"><svg><title>Panier</title></svg></a>'
            . '<a href="/e" title="Flux RSS"><i class="fa"></i></a>'
        );

        $this->assertSame([], $result['linksWithoutName']);
    }

    public function testALinkWithNothingToNameItIsReported(): void
    {
        $result = $this->analyze('<a href="/panier"><i class="fa fa-cart"></i></a><a href="/vide"><img src="/x.png" alt=""></a>');

        $this->assertSame(['<a href="/panier">', '<a href="/vide">'], $result['linksWithoutName']);
    }

    // One template producing the same unlabelled link down a listing is one fix, not forty rows
    public function testTheSameUnlabelledLinkIsReportedOnce(): void
    {
        $result = $this->analyze(str_repeat('<a href="/panier"><i class="fa"></i></a>', 5));

        $this->assertSame(['<a href="/panier">'], $result['linksWithoutName']);
    }

    public function testEveryWayOfLabellingAFieldIsAccepted(): void
    {
        $result = $this->analyze(
            '<label for="mail">Courriel</label><input type="email" id="mail" name="email">'
            . '<input type="text" name="search" aria-label="Rechercher">'
            . '<textarea name="message" title="Votre message"></textarea>'
            . '<select name="pays" aria-labelledby="paysLabel"><option>France</option></select>'
            . '<label>Une case<input type="checkbox" name="cgu"></label>'
        );

        $this->assertSame([], $result['fieldsWithoutLabel']);
    }

    public function testAFieldNothingLabelsIsReported(): void
    {
        $result = $this->analyze('<input type="text" name="nom"><input type="hidden" name="token"><button type="submit">Envoyer</button>');

        $this->assertSame(['<input name="nom">'], $result['fieldsWithoutLabel']);
    }

    // A "for" pointing at nothing labels nothing, which is exactly what test 11.1.2 asks
    public function testALabelPointingAtNoFieldDoesNotLabelIt(): void
    {
        $result = $this->analyze('<label for="courriel">Courriel</label><input type="email" name="email" id="mail">');

        $this->assertSame(['<input name="email">'], $result['fieldsWithoutLabel']);
    }

    public function testASkippedHeadingLevelIsReported(): void
    {
        $result = $this->analyze('<h1>Accueil</h1><h2>Nos services</h2><h4>Détail</h4>');

        $this->assertSame(['<h2> → <h4>'], $result['headingJumps']);
    }

    // Coming back up several levels starts a new section, which is not a jump
    public function testGoingBackUpSeveralLevelsIsNotAJump(): void
    {
        $result = $this->analyze('<h1>Accueil</h1><h2>Services</h2><h3>Détail</h3><h2>Contact</h2>');

        $this->assertSame([], $result['headingJumps']);
    }

    public function testATableDeclaringItsHeadersInEitherFormIsAccepted(): void
    {
        $result = $this->analyze(
            '<table><tr><th>Nom</th></tr><tr><td>Alice</td></tr></table>'
            . '<table><tr><td role="columnheader">Nom</td></tr><tr><td>Alice</td></tr></table>'
        );

        $this->assertSame(0, $result['tablesWithoutHeaders']);
    }

    public function testATableWithoutAnyHeaderIsCounted(): void
    {
        $result = $this->analyze('<table><tr><td>Alice</td></tr></table>');

        $this->assertSame(1, $result['tablesWithoutHeaders']);
    }

    public function testAMainLandmarkIsFoundByItsRoleToo(): void
    {
        $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('<html lang="fr"><body><div role="main"><p>Bonjour</p></div></body></html>', ['http_code' => 200]));

        $this->assertTrue(new AccessibilityClient($httpClient)->analyze('https://example.com/')['hasMainLandmark']);
    }

    // Accented text is read as the page serves it, not as ISO-8859-1 - see HtmlDocument
    public function testAnAccentedOffenceIsReadAsUtf8(): void
    {
        $httpClient = new MockHttpClient(fn (): MockResponse => new MockResponse('<html lang="fr"><body><iframe src="/vidéo"></iframe></body></html>', ['http_code' => 200]));

        $this->assertSame(['<iframe src="/vidéo">'], new AccessibilityClient($httpClient)->analyze('https://example.com/')['framesWithoutTitle']);
    }
}
