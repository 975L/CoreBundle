<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\BlockFixtureMediaAttacher;
use c975L\UiBundle\Service\BlockFixtureProvider;
use PHPUnit\Framework\TestCase;

class BlockFixtureProviderTest extends TestCase
{
    public function testGetFixturesCoversEveryBuiltInKind(): void
    {
        $fixtures = (new BlockFixtureProvider())->getFixtures();

        $this->assertSame(
            ['alert', 'audio', 'article', 'banner_title', 'button', 'card', 'document_download', 'form', 'image', 'image_compare', 'progress_bar', 'rich_snippet', 'slider', 'text_hook', 'text_readmore', 'text_section', 'video', 'video_iframe', 'hero', 'feature_bar', 'section_features', 'expertise_banner', 'process_steps', 'portfolio_grid', 'cta_band'],
            array_keys($fixtures)
        );
    }

    // "audio" carries no file nor format: both are auto-attached generically by BlockFixtureMediaAttacher (any "audio/*" mediaType), so the fixture only needs the player's own display fields
    public function testAudioFixtureOnlyCarriesDisplayFields(): void
    {
        $fixtures = (new BlockFixtureProvider())->getFixtures();

        $this->assertSame(['title', 'description', 'class'], array_keys($fixtures['audio']['']));
    }

    // "video" carries no file path nor format anymore: both come from the Media auto-attached by BlockFixtureMediaAttacher (any "video/*" mediaType), so the fixture only needs the player's own display fields, the same ones as "video_iframe" - "muted" so a gallery page full of blocks stays silent
    public function testVideoFixtureOnlyCarriesDisplayFields(): void
    {
        $fixtures = (new BlockFixtureProvider())->getFixtures();

        $this->assertSame(['options', 'title', 'description', 'width', 'height', 'class'], array_keys($fixtures['video']['']));
        $this->assertContains('muted', $fixtures['video']['']['options']);
    }

    // video_iframe just renders any URL in an <iframe> (see Video/Iframe.html.twig) - a raw video file navigated to directly would autoplay with sound via the browser's own player, so this uses the muted HTML wrapper instead of PLACEHOLDER_VIDEO directly. Leading "/" for the same reason as above.
    public function testVideoIframeFixtureUsesTheMutedVideoEmbedWrapper(): void
    {
        $fixtures = (new BlockFixtureProvider())->getFixtures();

        $this->assertSame('/' . BlockFixtureMediaAttacher::PLACEHOLDER_VIDEO_EMBED, $fixtures['video_iframe']['']['src']);
        $this->assertNotEmpty($fixtures['video_iframe']['']['title']);
        $this->assertNotEmpty($fixtures['video_iframe']['']['description']);
    }

    // alert has 4 style choices (info/success/warning/danger) - all shown side by side in the gallery
    public function testAlertFixtureCoversEveryStyleChoice(): void
    {
        $fixtures = (new BlockFixtureProvider())->getFixtures();

        $types = array_column($fixtures['alert'], 'type');
        sort($types);
        $this->assertSame(['danger', 'info', 'success', 'warning'], $types);
    }

    // button has 5 style choices (primary/secondary/success/danger/link) - all shown side by side in the gallery
    public function testButtonFixtureCoversEveryStyleChoice(): void
    {
        $fixtures = (new BlockFixtureProvider())->getFixtures();

        $types = array_column($fixtures['button'], 'type');
        sort($types);
        $this->assertSame(['danger', 'link', 'primary', 'secondary', 'success'], $types);
    }

    // Kinds without a meaningful style variant use '' as their single, unlabelled key
    public function testKindsWithoutStyleVariantsUseASingleUnlabelledVariant(): void
    {
        $fixtures = (new BlockFixtureProvider())->getFixtures();

        foreach (['audio', 'article', 'banner_title', 'card', 'document_download', 'form', 'image', 'image_compare', 'progress_bar', 'rich_snippet', 'text_hook', 'text_readmore', 'text_section', 'video', 'video_iframe', 'hero', 'feature_bar', 'section_features', 'expertise_banner', 'process_steps', 'portfolio_grid', 'cta_band'] as $kind) {
            $this->assertSame([''], array_keys($fixtures[$kind]), "Kind \"{$kind}\" should have a single unlabelled variant");
        }
    }

    // slider shows its default (single-slide-at-a-time) layout alongside the freeflow layout (see Slider.html.twig's layout="" param) so both are visible side by side in the gallery
    public function testSliderFixtureCoversDefaultAndFreeflowLayouts(): void
    {
        $fixtures = (new BlockFixtureProvider())->getFixtures();

        $this->assertSame(['', 'freeflow'], array_keys($fixtures['slider']));
        $this->assertSame('default', $fixtures['slider']['']['layout']);
        $this->assertSame('freeflow', $fixtures['slider']['freeflow']['layout']);
    }
}
