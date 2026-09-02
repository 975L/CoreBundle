<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Contract\PlaceholderMediaProviderInterface;
use c975L\UiBundle\Form\Block\BannerTitleType;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\BlockFixtureProvider;
use PHPUnit\Framework\TestCase;

class BlockFixtureProviderTest extends TestCase
{
    public function testGetFixturesCoversEveryBuiltInKind(): void
    {
        $fixtures = new BlockFixtureProvider()->getFixtures();

        $this->assertSame(
            ['alert', 'audio', 'article', 'banner_title', 'button', 'card', 'document_download', 'flip_card', 'form', 'image', 'image_compare', 'map', 'progress_bar', 'progress_tracker', 'contact_details', 'slider', 'text_hook', 'text_readmore', 'text_section', 'video', 'video_iframe', 'hero', 'feature_bar', 'section_features', 'expertise_banner', 'process_steps', 'portfolio_grid', 'cta_band', 'legal_model'],
            array_keys($fixtures)
        );
    }

    // A fixture is stored block data like any other: a key the FormType has since renamed, or a height off the step list, seeds a demo banner standing at the plain floor
    public function testBannerTitleFixtureStandsOnOneOfTheSteps(): void
    {
        $fixtures = new BlockFixtureProvider()->getFixtures();

        $this->assertSame(['title', 'level', 'height'], array_keys($fixtures['banner_title']['']));
        $this->assertContains($fixtures['banner_title']['']['height'], BannerTitleType::HEIGHT_CHOICES);
    }

    // "audio" carries no file nor format: both are auto-attached generically by BlockFixtureMediaAttacher (any "audio/*" mediaType), so the fixture only needs the player's own display fields
    public function testAudioFixtureOnlyCarriesDisplayFields(): void
    {
        $fixtures = new BlockFixtureProvider()->getFixtures();

        $this->assertSame(['title', 'description', 'class'], array_keys($fixtures['audio']['']));
    }

    // "video" carries no file path nor format anymore: both come from the Media auto-attached by BlockFixtureMediaAttacher (any "video/*" mediaType), so the fixture only needs the player's own display fields, the same ones as "video_iframe" - "muted" so a gallery page full of blocks stays silent
    public function testVideoFixtureOnlyCarriesDisplayFields(): void
    {
        $fixtures = new BlockFixtureProvider()->getFixtures();

        $this->assertSame(['options', 'title', 'description', 'width', 'height', 'class'], array_keys($fixtures['video']['']));
        $this->assertContains('muted', $fixtures['video']['']['options']);
    }

    // video_iframe just renders any URL in an <iframe> (see Video/Iframe.html.twig) - a raw video file navigated to directly would autoplay with sound via the browser's own player, so this uses the muted HTML wrapper the app declares (see PlaceholderMediaProviderInterface). Leading "/" for the same reason as above.
    public function testVideoIframeFixtureUsesTheDeclaredMutedVideoEmbedWrapper(): void
    {
        $fixtures = new BlockFixtureProvider($this->createPlaceholderMedia(['video_embed' => 'showcase/clip-embed.html']))->getFixtures();

        $this->assertSame('/showcase/clip-embed.html', $fixtures['video_iframe']['']['src']);
        $this->assertNotEmpty($fixtures['video_iframe']['']['title']);
        $this->assertNotEmpty($fixtures['video_iframe']['']['description']);
    }

    // The bundle ships no wrapper of its own: with none declared the preview shows the block's consent placeholder with nothing behind it, rather than a src pointing at a file that isn't there
    public function testVideoIframeFixtureCarriesNoSrcWhenNoneIsDeclared(): void
    {
        $this->assertSame('', new BlockFixtureProvider()->getFixtures()['video_iframe']['']['src']);
        $this->assertSame('', new BlockFixtureProvider($this->createPlaceholderMedia([]))->getFixtures()['video_iframe']['']['src']);
    }

    private function createPlaceholderMedia(array $media): PlaceholderMediaRegistry
    {
        $provider = $this->createStub(PlaceholderMediaProviderInterface::class);
        $provider->method('getPlaceholderMedia')->willReturn($media);

        $registry = new PlaceholderMediaRegistry();
        $registry->addProvider($provider);

        return $registry;
    }

    // alert has 4 style choices (info/success/warning/danger) - all shown side by side in the gallery
    public function testAlertFixtureCoversEveryStyleChoice(): void
    {
        $fixtures = new BlockFixtureProvider()->getFixtures();

        $types = array_column($fixtures['alert'], 'type');
        sort($types);
        $this->assertSame(['danger', 'info', 'success', 'warning'], $types);
    }

    // button has 5 style choices (primary/secondary/success/danger/link) - all shown side by side in the gallery
    public function testButtonFixtureCoversEveryStyleChoice(): void
    {
        $fixtures = new BlockFixtureProvider()->getFixtures();

        $types = array_column($fixtures['button'], 'type');
        sort($types);
        $this->assertSame(['danger', 'link', 'primary', 'secondary', 'success'], $types);
    }

    // card has 3 sizes (compact/default/big) - three counts to the row, shown side by side in the gallery so the difference reads as a row and not as a width
    public function testCardFixtureCoversEverySize(): void
    {
        $fixtures = new BlockFixtureProvider()->getFixtures();

        $this->assertSame(['Par défaut', 'Réduite', 'Grande'], array_keys($fixtures['card']));
        $this->assertSame(['compact', 'big'], [$fixtures['card']['Réduite']['size'], $fixtures['card']['Grande']['size']]);
    }

    // Kinds without a meaningful style variant use '' as their single, unlabelled key
    public function testKindsWithoutStyleVariantsUseASingleUnlabelledVariant(): void
    {
        $fixtures = new BlockFixtureProvider()->getFixtures();

        foreach (['audio', 'article', 'banner_title', 'document_download', 'form', 'image', 'image_compare', 'map', 'progress_bar', 'progress_tracker', 'contact_details', 'text_hook', 'text_readmore', 'text_section', 'video', 'video_iframe', 'feature_bar', 'section_features', 'expertise_banner', 'process_steps', 'portfolio_grid', 'cta_band'] as $kind) {
            $this->assertSame([''], array_keys($fixtures[$kind]), "Kind \"{$kind}\" should have a single unlabelled variant");
        }
    }

    // slider shows its default (single-slide-at-a-time) layout alongside the freeflow layout (see Slider.html.twig's layout="" param) so both are visible side by side in the gallery
    public function testSliderFixtureCoversDefaultAndFreeflowLayouts(): void
    {
        $fixtures = new BlockFixtureProvider()->getFixtures();

        $this->assertSame(['', 'freeflow'], array_keys($fixtures['slider']));
        $this->assertSame('default', $fixtures['slider']['']['layout']);
        $this->assertSame('freeflow', $fixtures['slider']['freeflow']['layout']);
    }

    // hero shows its ordinary layout alongside the background video, which fills the section and drops everything laid out beside the text - two looks of one kind, the same as the slider's above. The variant carries no field of its own: what tells them apart is the video BlockFixtureMediaAttacher attaches to this one only
    public function testHeroFixtureCoversTheOrdinaryLayoutAndTheBackgroundVideo(): void
    {
        $fixtures = new BlockFixtureProvider()->getFixtures();

        $this->assertSame(['', 'video'], array_keys($fixtures['hero']));
        $this->assertSame(array_keys($fixtures['hero']['']), array_keys($fixtures['hero']['video']));
    }
}
