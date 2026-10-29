<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Twig\BoolExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// The slider's zoom hands each image to Image:Zoom, naming the "-highres.webp" of a multi-size media and the stored file of any other one - the nested component is left as text here, so the tag and its attributes are what is read
class SliderZoomTest extends TestCase
{
    public function testWithoutZoomNoImageIsWrapped(): void
    {
        $html = $this->render(['media' => [$this->media(), $this->media()]]);

        $this->assertStringNotContainsString('Image:Zoom', $html);
    }

    public function testEachSlideOpensItsHighres(): void
    {
        $html = $this->render(['media' => [$this->media(['highresWidth' => 2400]), $this->media(['highresWidth' => 2400])], 'zoom' => 'true']);

        $this->assertSame(2, substr_count($html, '<twig:c975LUi:Image:Zoom src="/medias/insigne.webp" highres="/medias/insigne-highres.webp"'));
        $this->assertSame(2, substr_count($html, 'linkClass="slider-img-link"'));
    }

    // A media with no "-highres.webp" sibling opens its own file rather than a link to nothing
    public function testAMediaWithoutHighresOpensItsOwnFile(): void
    {
        $html = $this->render(['media' => [$this->media(), $this->media()], 'zoom' => 'true']);

        $this->assertSame(2, substr_count($html, 'highres="/medias/insigne.webp"'));
        $this->assertStringNotContainsString('-highres', $html);
    }

    // An image already linking to its slide's url keeps that link: a link cannot hold another one
    public function testAnImageLinkingToItsUrlIsNotZoomed(): void
    {
        $html = $this->render([
            'slides' => [['image' => $this->media(), 'url' => '/page'], ['image' => $this->media()]],
            'imageLinksToUrl' => true,
            'zoom' => 'true',
        ]);

        $this->assertStringContainsString('<a href="/page" class="slider-img-link">', $html);
        $this->assertSame(1, substr_count($html, '<twig:c975LUi:Image:Zoom'));
    }

    public function testAVideoIsNotZoomed(): void
    {
        $html = $this->render(['media' => [$this->media(['mimeType' => 'video/mp4']), $this->media(['mimeType' => 'video/mp4'])], 'zoom' => 'true']);

        $this->assertStringNotContainsString('Image:Zoom', $html);
    }

    public function testASingleMediaIsZoomed(): void
    {
        $html = $this->render(['media' => [$this->media(['highresWidth' => 2400])], 'zoom' => 'true']);

        $this->assertSame(1, substr_count($html, 'highres="/medias/insigne-highres.webp"'));
        $this->assertStringNotContainsString('Image:Image', $html);
    }

    // The same rule whatever the branch: a slide shown alone with a title or a text is zoomed like one without
    public function testASingleSlideWithATitleIsZoomed(): void
    {
        $html = $this->render(['slides' => [['image' => $this->media(), 'title' => 'Un titre']], 'zoom' => 'true']);

        $this->assertSame(1, substr_count($html, '<twig:c975LUi:Image:Zoom'));
        $this->assertStringContainsString('Un titre', $html);
    }

    // The caption stays beside the zoom rather than inside its link: a caption holding a link of its own would otherwise nest one <a> in another
    public function testTheCaptionIsLeftOutOfTheZoom(): void
    {
        $html = $this->render(['media' => [$this->media(['label' => '<a href="/source">Source</a>'])], 'zoom' => 'true']);

        $zoomEnd = strpos($html, ':priority="false" />');
        $caption = strpos($html, '<span class="img-below"><a href="/source">Source</a></span>');
        $this->assertNotFalse($zoomEnd);
        $this->assertNotFalse($caption);
        $this->assertGreaterThan($zoomEnd, $caption);
    }

    /** @param array<string, mixed> $values */
    private function media(array $values = []): object
    {
        return (object) ($values + [
            'alt' => 'Un insigne',
            'mimeType' => 'image/webp',
            'label' => null,
            'width' => '485',
            'height' => '666',
            'cssClasses' => [],
            'above' => false,
            'credits' => null,
            'rightsReserved' => null,
        ]);
    }

    // The bare environment the component renderer would otherwise bring, the nested <twig:...> call being left as text
    /** @param array<string, mixed> $context */
    private function render(array $context): string
    {
        // The namespace too, the controls being included by it
        $loader = new FilesystemLoader(dirname(__DIR__, 2) . '/templates');
        $loader->addPath(dirname(__DIR__, 2) . '/templates', 'c975LUi');
        $twig = new Environment($loader);
        $twig->addFilter(new TwigFilter('trans', static fn (?string $key): string => (string) $key));
        $twig->addFilter(new TwigFilter('to_bool', new BoolExtension()->toBool(...)));
        $twig->addFunction(new TwigFunction('vich_uploader_asset', static fn (mixed $value): string => '/medias/insigne.webp'));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/' . $path));

        return $twig->render('components/Slider/Slider.html.twig', $context + ['id' => 'slider']);
    }
}
