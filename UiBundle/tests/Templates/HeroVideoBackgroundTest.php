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

// An attached video fills the whole section, so it decides the hero's layout on its own - and it plays unattended,
// which is what the muting, the still under it and the reduced-motion way out all answer for
class HeroVideoBackgroundTest extends TestCase
{
    // Neither the checkbox nor a flat background has any say once a video is attached: it fills the section
    public function testAVideoTurnsTheBackgroundModeOnByItself(): void
    {
        $html = $this->render(['videoSrc' => '/media/generique.mp4', 'videoType' => 'video/mp4', 'background' => 'dark']);

        $this->assertStringContainsString('class="hero hero--has-bg"', $html);
        $this->assertStringNotContainsString('section--bg-dark', $html);
        // The medias laid out beside the text are dropped, exactly as a background image drops them
        $this->assertStringNotContainsString('hero__media', $html);
    }

    // No controls are printed, so a viewer given sound would have no way to turn it off
    public function testTheVideoPlaysMutedLoopingAndInline(): void
    {
        $html = $this->render(['videoSrc' => '/media/generique.mp4', 'videoType' => 'video/mp4']);

        $this->assertStringContainsString('<video class="hero__bg hero__bg--video" data-controller="heroVideo" muted loop playsinline preload="auto" aria-hidden="true">', $html);
        $this->assertStringContainsString('<source src="/media/generique.mp4" type="video/mp4">', $html);
        $this->assertStringNotContainsString('controls', $html);
    }

    // The video weighs megabytes and paints nothing until it downloads: the image uploaded beside it is the same
    // background as any other hero's, and it is what the page has as its LCP element in the meantime
    public function testAnImageUploadedBesideTheVideoIsPaintedUnderItAsTheStill(): void
    {
        $html = $this->render(['videoSrc' => '/media/generique.mp4', 'videoType' => 'video/mp4', 'src' => '/media/still.webp']);

        $this->assertStringContainsString('<img class="hero__bg" src="/media/still.webp" alt="" aria-hidden="true" fetchpriority="high">', $html);
        // Both are absolutely placed: the still has to come first for the video to cover it once it plays
        $this->assertLessThan(strpos($html, '<video'), strpos($html, '<img'), 'The still must be printed before the video it stands behind.');
    }

    // A video standing alone is a legitimate hero: its own first frame is what fills the section until it plays
    public function testAVideoNeedsNoStillToFillTheSection(): void
    {
        $html = $this->render(['videoSrc' => '/media/generique.mp4', 'videoType' => 'video/mp4']);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('hero__bg--video', $html);
    }

    // Every hero stored before the field existed carries no video, and must render exactly as it did
    public function testAHeroWithNoVideoPrintsNoVideoTag(): void
    {
        $html = $this->render(['src' => '/media/still.webp']);

        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('hero__bg', $html);
        $this->assertStringContainsString('hero__media', $html);
    }

    // A background video's own footage commonly carries the title already, leaving the hero nothing to say over it.
    // Printed all the same, the heading would take the page's <h1> away from whatever does title it, and leave a
    // screen reader announcing a heading with nothing to read
    public function testATitlelessHeroPrintsNoHeadingAtAll(): void
    {
        $html = $this->render(['videoSrc' => '/media/generique.mp4', 'videoType' => 'video/mp4', 'title' => '']);

        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringNotContainsString('hero__title', $html);
        // Nothing left for it to hold, so the text column goes with the heading rather than being printed empty
        $this->assertStringNotContainsString('hero__text', $html);
    }

    // Trix stores a cleared editor as an empty block tag, which is truthy - hence a check on the text held, not on the markup
    public function testAClearedTrixTitleCountsAsNoTitle(): void
    {
        $html = $this->render(['videoSrc' => '/media/generique.mp4', 'title' => '<div><br></div>']);

        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringNotContainsString('hero__title', $html);
    }

    // Every other part of the text column stands on its own: only the heading goes when there is no title
    public function testATitlelessHeroStillPrintsWhateverElseItHolds(): void
    {
        $html = $this->render(['videoSrc' => '/media/generique.mp4', 'title' => '', 'badge' => 'Films']);

        $this->assertStringContainsString('<div class="hero__text">', $html);
        $this->assertStringContainsString('<span class="hero__badge">Films</span>', $html);
        $this->assertStringNotContainsString('hero__title', $html);
    }

    // The component is handed one video and one image list: the adapter is what tells the uploaded files apart
    public function testTheBlockAdapterSplitsTheUploadedMediasByMimetype(): void
    {
        $adapter = file_get_contents(\dirname(__DIR__, 2) . '/templates/blocks/Hero.html.twig');

        $this->assertStringContainsString("block.media|filter(item => item.mimeType starts with 'video/')|first", $adapter);
        $this->assertStringContainsString("block.media|filter(item => item.mimeType starts with 'image/')", $adapter);
        $this->assertStringContainsString('videoSrc="{{ video ? vich_uploader_asset(video) : \'\' }}"', $adapter);
        $this->assertStringContainsString('videoType="{{ video ? video.mimeType : \'\' }}"', $adapter);
    }

    // Twig resolves the filters and functions at compile time, so they must exist even when never reached
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        $twig->addFilter(new TwigFilter('trix_inline', static fn (?string $value): string => (string) $value, ['is_safe' => ['html']]));
        $twig->addFilter(new TwigFilter('to_bool', static fn (mixed $value): bool => (bool) $value));
        $twig->addFunction(new TwigFunction('vich_uploader_asset', static fn (mixed $media): string => '/media/logo.webp'));

        return $twig->render('components/Hero/Hero.html.twig', $context + ['title' => 'Un titre']);
    }
}
