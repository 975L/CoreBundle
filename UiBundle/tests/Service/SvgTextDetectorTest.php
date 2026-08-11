<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\SvgTextDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class SvgTextDetectorTest extends TestCase
{
    private string $directory;

    private SvgTextDetector $detector;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/svg-text-detector-test-' . uniqid();
        new Filesystem()->mkdir($this->directory);
        $this->detector = new SvgTextDetector();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    private function write(string $contents, string $name = 'logo.svg'): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $contents);

        return $path;
    }

    private function svg(string $body): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">' . $body . '</svg>';
    }

    // Asked apart from drawsText() so a caller can tell "not an SVG" from "an SVG with nothing to fix"
    public function testIsSvgSeparatesAVectorizedSvgFromANonSvg(): void
    {
        $vectorized = $this->write($this->svg('<path d="M0 0 L1 1 Z"/>'));
        $rasterized = $this->write("\x00\x00\x01\x00", 'favicon.ico');

        $this->assertTrue($this->detector->isSvg($vectorized));
        $this->assertFalse($this->detector->isSvg($rasterized));
        $this->assertFalse($this->detector->drawsText($vectorized));
    }

    public function testDrawsTextIsFalseForAVectorizedSvg(): void
    {
        $path = $this->write($this->svg('<path d="M0 0 L10 10 Z"/>'));

        $this->assertFalse($this->detector->drawsText($path));
        $this->assertSame([], $this->detector->fontFamilies($path));
    }

    public function testDrawsTextIsTrueForATextElement(): void
    {
        $path = $this->write($this->svg('<text x="0" y="0">975L</text>'));

        $this->assertTrue($this->detector->drawsText($path));
    }

    public function testTspanAndTextPathCountToo(): void
    {
        $tspan = $this->write($this->svg('<tspan>975L</tspan>'), 'tspan.svg');
        $textPath = $this->write($this->svg('<textPath href="#p">975L</textPath>'), 'text-path.svg');

        $this->assertTrue($this->detector->drawsText($tspan));
        $this->assertTrue($this->detector->drawsText($textPath));
    }

    public function testFontFamiliesReadsTheAttribute(): void
    {
        $path = $this->write($this->svg('<text font-family="Riffic Free">975L</text>'));

        $this->assertSame(['Riffic Free'], $this->detector->fontFamilies($path));
    }

    // Only the head of the list is kept: the rest is what the renderer falls back to precisely because the first one is missing
    public function testFontFamiliesDropsTheFallbackList(): void
    {
        $path = $this->write($this->svg('<text style="font-family:\'Riffic Free\', sans-serif">975L</text>'));

        $this->assertSame(['Riffic Free'], $this->detector->fontFamilies($path));
    }

    public function testFontFamiliesAreDeduplicatedAcrossNodes(): void
    {
        $path = $this->write($this->svg(
            '<text font-family="Riffic Free">975</text><text font-family="Eagle Lake">L</text><text font-family="Riffic Free">!</text>'
        ));

        $this->assertSame(['Riffic Free', 'Eagle Lake'], $this->detector->fontFamilies($path));
    }

    // Text with no family of its own still draws with a font, it just doesn't say which
    public function testTextWithoutAFamilyIsStillDetected(): void
    {
        $path = $this->write($this->svg('<text>975L</text>'));

        $this->assertTrue($this->detector->drawsText($path));
        $this->assertSame([], $this->detector->fontFamilies($path));
    }

    // An icon role's stored file carries the role's own extension while still holding the uploaded SVG markup
    public function testAnSvgStoredUnderAnIconExtensionIsStillRead(): void
    {
        $path = $this->write($this->svg('<text>975L</text>'), 'favicon.ico');

        $this->assertTrue($this->detector->drawsText($path));
    }

    public function testNonSvgFilesAreIgnored(): void
    {
        $missing = $this->directory . '/nope.svg';
        $png = $this->write("\x89PNG\r\n\x1a\n", 'raster.png');
        $html = $this->write('<html><body>not an svg at all</body></html>', 'page.html');

        $this->assertFalse($this->detector->drawsText($missing));
        $this->assertFalse($this->detector->drawsText($png));
        $this->assertFalse($this->detector->drawsText($html));
    }

    // "<svg" appearing in a document whose root is something else must not be parsed as one
    public function testAnHtmlPageEmbeddingAnSvgIsNotTreatedAsOne(): void
    {
        $path = $this->write('<html><body><svg><text>975L</text></svg></body></html>', 'page.svg');

        $this->assertFalse($this->detector->drawsText($path));
    }

    public function testMalformedMarkupIsIgnored(): void
    {
        $path = $this->write('<svg><text>975L</svg>');

        $this->assertFalse($this->detector->drawsText($path));
    }
}
