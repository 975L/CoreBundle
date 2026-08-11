<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Form\Block\BannerTitleType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The banner writes no CSS of its own: the height an editor picks is a step naming a class, and the picture an element the stylesheet lays over the whole block. A step reaching no rule is a stored value standing for nothing, and a picture losing its rule covers the title instead of sitting behind it
class BannerTitleLayoutTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'styles.css' => ['styles.css'],
            'styles.min.css' => ['styles.min.css'],
        ];
    }

    // Every step the picker offers has to raise the floor, or a banner set on it stands exactly as an "automatic" one does
    #[DataProvider('stylesheetProvider')]
    public function testEveryHeightStepPointsTheFloorAtItsOwnToken(string $file): void
    {
        $css = $this->normalize($file);

        foreach (BannerTitleType::HEIGHT_CHOICES as $step) {
            $this->assertMatchesRegularExpression(
                sprintf('/\.banner-title--height-%s\{min-height:var\(--banner-title-height-%s\)[;}]/', $step, $step),
                $css,
                sprintf('"%s" leaves the "%s" height step without a rule, so a banner set on it stands at nothing of its own.', $file, $step)
            );
        }
    }

    // The step raises the banner's own floor, and both are one class deep: source order is the whole of what separates them, so a step read first is a step the floor overrules
    #[DataProvider('stylesheetProvider')]
    public function testTheStepsAreReadAfterTheFloorTheyRaise(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression('/\.banner-title\{[^}]*min-height:200px[;}]/', $css, sprintf('"%s" drops the banner\'s own floor.', $file));
        $this->assertLessThan(
            strpos($css, '.banner-title--height-small'),
            strpos($css, 'min-height:200px'),
            sprintf('"%s" declares the steps before the floor, which then wins over every one of them.', $file)
        );
    }

    // A floor and never a cap: a title long enough to need more room than its step gets it, where a max-height would crop it
    #[DataProvider('stylesheetProvider')]
    public function testNoStepCapsTheBanner(string $file): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/\.banner-title(--height-[a-z]+)?\{[^}]*max-height:/',
            $this->normalize($file),
            sprintf('"%s" caps the banner, which crops a title needing more room than its step.', $file)
        );
    }

    // Cropped exactly as the background-image it replaces was, and out of flow inside the banner's own positioning context
    #[DataProvider('stylesheetProvider')]
    public function testThePictureCoversTheWholeBannerAndIsCropped(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression(
            '/\.banner-title-img\{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center[;}]/',
            $css,
            sprintf('"%s" no longer lays the picture over the banner, which the background-image it replaces always filled.', $file)
        );
        $this->assertMatchesRegularExpression(
            '/\.banner-title\{position:relative[;}]/',
            $css,
            sprintf('"%s" leaves the banner unpositioned, so the picture is laid over whichever ancestor is.', $file)
        );
    }

    // The picture is out of flow and painted after everything still in it, so the overlay takes a position of its own to stay on top
    #[DataProvider('stylesheetProvider')]
    public function testTheOverlayIsPositionedSoItPaintsOverThePicture(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.banner-title-overlay\{position:relative[;}]/',
            $this->normalize($file),
            sprintf('"%s" no longer positions the overlay, so the picture paints over the title.', $file)
        );
    }

    // The page's one vertical step, on the top edge only: the room below the banner belongs to the block that follows it, and a gap of its own here is added to that block's step and parts the pair by two
    #[DataProvider('stylesheetProvider')]
    public function testTheBannerTakesItsStepFromTheSharedRhythmAndOnlyAboveItself(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertMatchesRegularExpression(
            '/\.banner-title\{[^}]*margin-block-start:var\(--section-space-tight,clamp\(24px,4vw,48px\)\)[;}]/',
            $css,
            sprintf('"%s" no longer reads the banner\'s step from the shared rhythm, which a site retunes from its own theme.css.', $file)
        );
        $this->assertMatchesRegularExpression(
            '/\.banner-title\{[^}]*margin-block-end:0[;}]/',
            $css,
            sprintf('"%s" hangs a gap under the banner, which is added to the step the next block already declares above itself.', $file)
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.banner-title\{[^}]*margin-(top|bottom|block):(?!0)/',
            $css,
            sprintf('"%s" parts the banner from its neighbours with a length of its own, beside the step above.', $file)
        );
    }

    // Normalized so the same assertions hold whatever the compiler wrapped
    private function normalize(string $file): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($this->path('public/css/' . $file)));
        $css = (string) preg_replace('/\s+/', ' ', $css);

        return (string) preg_replace('/ *([{};:,>]) */', '$1', $css);
    }

    private function path(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $relativePath));

        return $path;
    }
}
