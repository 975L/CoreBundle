<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\VichWatermarkableInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Service\ImageWatermarker;
use Imagine\Gd\Imagine;
use Imagine\Image\ImageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class ImageWatermarkerTest extends TestCase
{
    private const array WHITE = [255, 255, 255];
    private const array BLACK = [0, 0, 0];
    private const array RED = [255, 0, 0];

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/image-watermarker-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    // --- picking a signature -------------------------------------------------------------------------------

    public function testPrepareStampsNothingForAnEntityAskingForNone(): void
    {
        $this->writeLogo('on-light.png');
        $this->writeLogo('on-dark.png');

        $watermarker = $this->createWatermarker(['on-light.png', 'on-dark.png']);

        $this->assertNull($watermarker->prepare(new WatermarkableStub(false), $this->createImage(400, 400, self::WHITE)));
    }

    public function testPrepareStampsNothingWhenNoSignatureHasBeenUploaded(): void
    {
        $watermarker = $this->createWatermarker([null, null]);

        $this->assertNull($watermarker->prepare(new WatermarkableStub(), $this->createImage(400, 400, self::WHITE)));
    }

    // A row pointing at a file that has since gone from disk is the same as no row at all - a missing signature is not worth crashing an upload over
    public function testPrepareStampsNothingWhenTheUploadedSignatureIsGoneFromDisk(): void
    {
        $watermarker = $this->createWatermarker(['vanished.png', null]);

        $this->assertNull($watermarker->prepare(new WatermarkableStub(), $this->createImage(400, 400, self::WHITE)));
    }

    public function testPrepareTakesTheLightSignatureOnADarkCorner(): void
    {
        $this->writeLogo('on-light.png');
        $this->writeLogo('on-dark.png');

        $watermark = $this->createWatermarker(['on-light.png', 'on-dark.png'])
            ->prepare(new WatermarkableStub(), $this->createImage(400, 400, self::BLACK))
        ;

        $this->assertNotNull($watermark);
        $this->assertSame($this->logoWidth('on-dark.png'), $watermark->logo->getSize()->getWidth());
    }

    public function testPrepareTakesTheDarkSignatureOnALightCorner(): void
    {
        $this->writeLogo('on-light.png');
        $this->writeLogo('on-dark.png');

        $watermark = $this->createWatermarker(['on-light.png', 'on-dark.png'])
            ->prepare(new WatermarkableStub(), $this->createImage(400, 400, self::WHITE))
        ;

        $this->assertNotNull($watermark);
        $this->assertSame($this->logoWidth('on-light.png'), $watermark->logo->getSize()->getWidth());
    }

    // The whole point of measuring rather than settling on one signature site-wide: a photo can be bright everywhere except the very corner the logo lands in, and it is that corner the signature has to be read against
    public function testPrepareOnlyLooksAtTheCornerTheLogoWillOccupy(): void
    {
        $this->writeLogo('on-light.png');
        $this->writeLogo('on-dark.png');

        // Bright almost everywhere, so a whole-image average would come out well over the threshold and pick the dark signature
        $image = $this->createImage(400, 400, self::WHITE);
        $this->paint($image, 320, 320, 80, 80, self::BLACK);

        $watermark = $this->createWatermarker(['on-light.png', 'on-dark.png'])
            ->prepare(new WatermarkableStub(), $image)
        ;

        $this->assertNotNull($watermark);
        $this->assertSame($this->logoWidth('on-dark.png'), $watermark->logo->getSize()->getWidth());
    }

    // A site that uploaded only one signature gets it on every photo: refusing to stamp would silently drop a watermark the admin did ask for
    public function testPrepareFallsBackToTheOnlySignatureUploaded(): void
    {
        $this->writeLogo('on-light.png');

        $watermark = $this->createWatermarker(['on-light.png', null])
            ->prepare(new WatermarkableStub(), $this->createImage(400, 400, self::BLACK))
        ;

        $this->assertNotNull($watermark);
        $this->assertSame($this->logoWidth('on-light.png'), $watermark->logo->getSize()->getWidth());
    }

    // --- where it lands ------------------------------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: int, 2: int}>
     */
    public static function cornerProvider(): array
    {
        // A 400x400 image, a logo laid at 25% of its width (100px) 10% (40px) inside the corner: the centre of the stamped logo then falls on these coordinates
        return [
            'top left' => [VichWatermarkableInterface::POSITION_TOP_LEFT, 90, 90],
            'top right' => [VichWatermarkableInterface::POSITION_TOP_RIGHT, 310, 90],
            'bottom left' => [VichWatermarkableInterface::POSITION_BOTTOM_LEFT, 90, 310],
            'bottom right' => [VichWatermarkableInterface::POSITION_BOTTOM_RIGHT, 310, 310],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cornerProvider')]
    public function testStampPlacesTheLogoInTheCornerAskedFor(string $position, int $x, int $y): void
    {
        $this->writeLogo('on-light.png', 100, 100, false);

        $watermarker = $this->createWatermarker(['on-light.png', null], [
            'ui-watermark-width' => '25',
            'ui-watermark-margin' => '10',
        ]);

        $image = $this->createImage(400, 400, self::WHITE);
        $watermark = $watermarker->prepare(new WatermarkableStub(true, $position), $image);

        $this->assertNotNull($watermark);
        $watermarker->stamp($image, $watermark);

        $this->assertSame(self::RED, $this->colorAt($image, $x, $y), 'The logo is not in the corner asked for.');

        // The three other corners stay untouched, so a stamp cannot pass by landing everywhere
        foreach (self::cornerProvider() as $corner) {
            if ($corner[0] !== $position) {
                $this->assertSame(self::WHITE, $this->colorAt($image, $corner[1], $corner[2]));
            }
        }
    }

    // Expressed as a share of the width rather than in pixels, so one signature serves a 2048px export and a 600px thumbnail alike
    public function testStampSizesTheLogoAsAShareOfTheImageWidth(): void
    {
        $this->writeLogo('on-light.png', 100, 50, false);

        $watermarker = $this->createWatermarker(['on-light.png', null], [
            'ui-watermark-width' => '50',
            'ui-watermark-margin' => '0',
            'ui-watermark-position' => VichWatermarkableInterface::POSITION_TOP_LEFT,
        ]);

        $image = $this->createImage(400, 400, self::WHITE);
        $watermark = $watermarker->prepare(new WatermarkableStub(), $image);

        $this->assertNotNull($watermark);
        $watermarker->stamp($image, $watermark);

        // 50% of 400 is 200 wide, and the logo's own 2:1 proportions make it 100 high
        $this->assertSame(self::RED, $this->colorAt($image, 199, 99), 'The logo stops short of its expected size.');
        $this->assertSame(self::WHITE, $this->colorAt($image, 201, 99), 'The logo runs past its expected width.');
        $this->assertSame(self::WHITE, $this->colorAt($image, 199, 101), 'The logo runs past its expected height.');
    }

    // A signature is a transparent PNG: laid as an opaque rectangle it would black out the corner it is meant to sign
    public function testStampKeepsTheLogoTransparency(): void
    {
        $this->writeLogo('on-light.png', 100, 100, true);

        $watermarker = $this->createWatermarker(['on-light.png', null], [
            'ui-watermark-width' => '50',
            'ui-watermark-margin' => '0',
            'ui-watermark-position' => VichWatermarkableInterface::POSITION_TOP_LEFT,
        ]);

        $image = $this->createImage(400, 400, self::WHITE);
        $watermark = $watermarker->prepare(new WatermarkableStub(), $image);

        $this->assertNotNull($watermark);
        $watermarker->stamp($image, $watermark);

        // The logo's left half is fully transparent, its right half opaque red - the photo has to show through the one and be covered by the other
        $this->assertSame(self::WHITE, $this->colorAt($image, 50, 100), 'The transparent half of the logo covered the photo.');
        $this->assertSame(self::RED, $this->colorAt($image, 150, 100), 'The opaque half of the logo did not cover the photo.');
    }

    // Squeezing a signature into an image it cannot fit would produce something unreadable, which is worse than the unsigned image
    public function testStampLeavesAnImageTooSmallForTheLogoAlone(): void
    {
        $this->writeLogo('on-light.png', 100, 100, false);

        $watermarker = $this->createWatermarker(['on-light.png', null], [
            'ui-watermark-width' => '60',
            'ui-watermark-margin' => '30',
        ]);

        $image = $this->createImage(400, 400, self::WHITE);
        $watermark = $watermarker->prepare(new WatermarkableStub(), $image);

        $this->assertNotNull($watermark);
        $watermarker->stamp($image, $watermark);

        $this->assertSame(self::WHITE, $this->colorAt($image, 200, 200));
        $this->assertSame(self::WHITE, $this->colorAt($image, 380, 380));
    }

    // --- settings ------------------------------------------------------------------------------------------

    // The corner a batch picked has to win over the site-wide one, that being the whole point of asking per upload
    public function testPositionAskedForByTheEntityWinsOverTheSiteSetting(): void
    {
        $this->writeLogo('on-light.png', 100, 100, false);

        $watermarker = $this->createWatermarker(['on-light.png', null], [
            'ui-watermark-width' => '25',
            'ui-watermark-margin' => '10',
            'ui-watermark-position' => VichWatermarkableInterface::POSITION_BOTTOM_RIGHT,
        ]);

        $image = $this->createImage(400, 400, self::WHITE);
        $watermark = $watermarker->prepare(new WatermarkableStub(true, VichWatermarkableInterface::POSITION_TOP_LEFT), $image);

        $this->assertNotNull($watermark);
        $this->assertSame(VichWatermarkableInterface::POSITION_TOP_LEFT, $watermark->position);
    }

    // Config values are admin-typed text: a corner nobody ever named leaves the bottom right in place rather than throwing an upload away
    public function testAnUnknownCornerFallsBackToTheBottomRight(): void
    {
        $this->writeLogo('on-light.png', 100, 100, false);

        $watermarker = $this->createWatermarker(['on-light.png', null], ['ui-watermark-position' => 'middle-of-nowhere']);

        $watermark = $watermarker->prepare(new WatermarkableStub(true, 'somewhere-else'), $this->createImage(400, 400, self::WHITE));

        $this->assertNotNull($watermark);
        $this->assertSame(VichWatermarkableInterface::POSITION_BOTTOM_RIGHT, $watermark->position);
    }

    // Same reason: a width of "0", "-5" or "grand" would size the logo out of existence, where the shipped default at least produces a readable signature
    public function testAnUnusableSizeFallsBackToTheShippedDefault(): void
    {
        $this->writeLogo('on-light.png', 100, 100, false);

        $watermarker = $this->createWatermarker(['on-light.png', null], [
            'ui-watermark-width' => 'grand',
            'ui-watermark-margin' => '-5',
        ]);

        $watermark = $watermarker->prepare(new WatermarkableStub(), $this->createImage(400, 400, self::WHITE));

        $this->assertNotNull($watermark);
        $this->assertSame(13.75, $watermark->width);
        $this->assertSame(0.42, $watermark->margin);
    }

    // --- helpers -------------------------------------------------------------------------------------------

    /**
     * @param array{0: ?string, 1: ?string} $logos   the filenames uploaded for the on-light and on-dark roles, relative to public/
     * @param array<string, string>         $configs
     */
    private function createWatermarker(array $logos, array $configs = []): ImageWatermarker
    {
        $filenames = [
            Media::ROLE_WATERMARK_ON_LIGHT => $logos[0],
            Media::ROLE_WATERMARK_ON_DARK => $logos[1],
        ];

        $repository = $this->createStub(MediaRepository::class);
        $repository->method('findOneByRole')->willReturnCallback(
            static function (string $role) use ($filenames): ?Media {
                if (null === ($filenames[$role] ?? null)) {
                    return null;
                }

                $media = new Media();
                $media->setRole($role);
                $media->setFilename($filenames[$role]);

                return $media;
            }
        );

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnCallback(static fn (string $key): bool => array_key_exists($key, $configs));
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => $configs[$key] ?? null);
        $configService->method('getBool')->willReturnCallback(static fn ($value): bool => filter_var($value, \FILTER_VALIDATE_BOOLEAN));

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        return new ImageWatermarker($configService, $repository, $parameterBag);
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function createImage(int $width, int $height, array $rgb): ImageInterface
    {
        $path = $this->projectDir . '/public/source-' . uniqid() . '.png';
        $resource = imagecreatetruecolor($width, $height);
        imagefill($resource, 0, 0, imagecolorallocate($resource, ...$rgb));
        imagepng($resource, $path);

        return new Imagine()->open($path);
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function paint(ImageInterface $image, int $x, int $y, int $width, int $height, array $rgb): void
    {
        $patch = $this->createImage($width, $height, $rgb);
        $image->paste($patch, new \Imagine\Image\Point($x, $y));
    }

    // A logo wide enough to be told apart once resized: opaque red, or red on its right half only with a fully transparent left half when transparency is what is being checked
    private function writeLogo(string $filename, int $width = 100, int $height = 50, bool $halfTransparent = false): void
    {
        $resource = imagecreatetruecolor($width, $height);
        imagealphablending($resource, false);
        imagesavealpha($resource, true);

        $transparent = imagecolorallocatealpha($resource, 0, 0, 0, 127);
        imagefilledrectangle($resource, 0, 0, $width - 1, $height - 1, $transparent);

        $red = imagecolorallocate($resource, ...self::RED);
        $from = $halfTransparent ? (int) ($width / 2) : 0;
        imagefilledrectangle($resource, $from, 0, $width - 1, $height - 1, $red);

        imagepng($resource, $this->projectDir . '/public/' . $filename);
    }

    private function logoWidth(string $filename): int
    {
        return (int) getimagesize($this->projectDir . '/public/' . $filename)[0];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function colorAt(ImageInterface $image, int $x, int $y): array
    {
        $color = $image->getColorAt(new \Imagine\Image\Point($x, $y));

        return [
            $color->getValue(\Imagine\Image\Palette\Color\ColorInterface::COLOR_RED),
            $color->getValue(\Imagine\Image\Palette\Color\ColorInterface::COLOR_GREEN),
            $color->getValue(\Imagine\Image\Palette\Color\ColorInterface::COLOR_BLUE),
        ];
    }
}

// Nothing in this bundle wants a watermark (GalleryBundle's GalleryMedia is the first consumer), so the contract needs an entity of its own to be tested at all
class WatermarkableStub implements VichWatermarkableInterface
{
    public function __construct(
        private readonly bool $wants = true,
        private readonly ?string $position = null,
    ) {
    }

    public function getImageWidth(): int
    {
        return 400;
    }

    public function wantsWatermark(): bool
    {
        return $this->wants;
    }

    public function getWatermarkPosition(): ?string
    {
        return $this->position;
    }
}
