<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\VichMultiSizeImageInterface;
use c975L\UiBundle\Contract\VichOriginalKeepableInterface;
use c975L\UiBundle\Contract\VichWatermarkableInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Listener\VichImageResizeListener;
use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Service\ImageDimensionsReader;
use c975L\UiBundle\Service\ImageWatermarker;
use c975L\UiBundle\Service\SvgRasterizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class VichImageResizeListenerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/vich-image-resize-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
    }

    // Recursive: a kept original lands under its own directory outside public/, and the stored files themselves sit in nested paths
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    private function createMapping(): PropertyMapping
    {
        $mapping = new PropertyMapping('file', 'filename');
        $mapping->setMapping(['upload_destination' => $this->projectDir . '/public', 'uri_prefix' => '']);

        return $mapping;
    }

    // Regression: a sync roundtrip re-feeds an .ico back in, which GD can't decode and used to crash the import
    public function testOnPostUploadDoesNotCrashWhenFixedIconFileIsAlreadyInTargetFormat(): void
    {
        $icoPath = $this->projectDir . '/public/favicon.ico';
        file_put_contents($icoPath, 'not-a-gd-decodable-ico');

        $media = new Media();
        $media->setRole(Media::ROLE_FAVICON);
        $media->setFilename('favicon.ico');
        $media->setFile(new File($icoPath));

        $listener = $this->createListener();
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertSame('not-a-gd-decodable-ico', file_get_contents($icoPath));
    }

    // Every <img> needs its intrinsic size to reserve its box before the file arrives, so an upload records it - the "img-responsive" class only keeps the image fluid, it can't tell the browser the proportions in advance
    public function testOnPostUploadStoresTheStoredFileDimensions(): void
    {
        $pngPath = $this->projectDir . '/public/photo.png';
        imagepng(imagecreatetruecolor(1200, 800), $pngPath);

        $media = new Media();
        $media->setFilename('photo.png');
        $media->setFile(new File($pngPath));

        $listener = $this->createListener();
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        // Not the uploaded 1200x800: processImage() downscales to the entity's own getImageWidth() first, and the recorded size has to describe the file actually served
        $this->assertSame((string) $media->getImageWidth(), $media->getWidth());
        $this->assertSame((string) (int) ($media->getImageWidth() * 800 / 1200), $media->getHeight());
    }

    // Regression: an upload narrower than the entity's target width used to be enlarged to it, producing a softer and heavier file out of pixels that were never there - and, on a VichMultiSizeImageInterface entity, a stored "medium" bigger than the "highres" derivative, which has always capped itself at the original
    public function testOnPostUploadNeverEnlargesAnUploadSmallerThanTheTargetWidth(): void
    {
        $pngPath = $this->projectDir . '/public/small.png';
        imagepng(imagecreatetruecolor(300, 200), $pngPath);

        $media = new Media();
        $media->setFilename('small.png');
        $media->setFile(new File($pngPath));

        $listener = $this->createListener();
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertLessThan($media->getImageWidth(), 300, 'The uploaded file has to be narrower than the target width, or this test checks nothing.');
        $this->assertSame('300', $media->getWidth());
        $this->assertSame('200', $media->getHeight());

        $dimensions = getimagesize($pngPath);
        $this->assertSame(300, $dimensions[0]);
        $this->assertSame(200, $dimensions[1]);
    }

    // Regression: the stored file carries the role's own .ico extension whatever was uploaded (see UiMediaNamer), so deciding on the extension left every raster favicon unconverted - stored as the uploaded png under an .ico name, neither cropped to 48x48 nor wrapped as a real icon
    public function testOnPostUploadConvertsARasterUploadedForTheFaviconRole(): void
    {
        $iconPath = $this->projectDir . '/public/favicon.ico';
        imagepng(imagecreatetruecolor(256, 256), $iconPath);

        $media = new Media();
        $media->setRole(Media::ROLE_FAVICON);
        $media->setFilename('favicon.ico');
        $media->setFile(new File($iconPath));

        $listener = $this->createListener();
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        // The ICO signature: reserved 0, type 1 (icon), 1 image
        $this->assertSame("\0\0\1\0\1\0", substr((string) file_get_contents($iconPath), 0, 6));

        $dimensions = getimagesize($iconPath);
        $this->assertSame(48, $dimensions[0]);
        $this->assertSame(48, $dimensions[1]);
    }

    // An SVG uploaded as favicon/apple-touch-icon is rasterized upstream, then converted like any other upload - the stored file must be the role's own format, never the SVG the admin picked (see SvgRasterizer)
    public function testOnPostUploadRasterizesAnSvgUploadedForAFixedIconRole(): void
    {
        if (!SvgRasterizer::isSupported()) {
            $this->markTestSkipped('ext-imagick with SVG support is needed to rasterize an SVG.');
        }

        // Named after the role's target format already, the namer having rewritten the extension before the upload landed (see UiMediaNamer)
        $iconPath = $this->projectDir . '/public/apple-touch-icon.png';
        file_put_contents($iconPath, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="45" fill="#e63946"/></svg>');

        $media = new Media();
        $media->setRole(Media::ROLE_APPLE_TOUCH_ICON);
        $media->setFilename('apple-touch-icon.png');
        $media->setFile(new File($iconPath));

        $listener = $this->createListener();
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $dimensions = getimagesize($iconPath);
        $this->assertSame(IMAGETYPE_PNG, $dimensions[2]);
        $this->assertSame(114, $dimensions[0]);
        $this->assertSame(114, $dimensions[1]);
    }

    // An svg is never resized (GD can't decode it), but it still has to carry its dimensions
    public function testOnPostUploadStoresDimensionsOfAFileItDoesNotProcess(): void
    {
        $svgPath = $this->projectDir . '/public/logo.svg';
        file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 150"></svg>');

        $media = new Media();
        $media->setFilename('logo.svg');
        $media->setFile(new File($svgPath));

        $listener = $this->createListener();
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertSame('300', $media->getWidth());
        $this->assertSame('150', $media->getHeight());
        $this->assertStringContainsString('<svg', (string) file_get_contents($svgPath));
    }

    // The thumbnail holds the whole image, its longest side capped at getThumbnailSize(): what a grid shows in a square is settled in CSS, and a file cropped square here could never give the cut pixels back
    public function testOnPostUploadThumbnailsAMultiSizeImageWithoutCroppingIt(): void
    {
        $pngPath = $this->projectDir . '/public/multi-size.png';
        imagepng(imagecreatetruecolor(1200, 800), $pngPath);

        $media = new MultiSizeImageStub($pngPath);

        $listener = $this->createListener();
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $thumbnail = getimagesize($this->projectDir . '/public/multi-size-thumb.webp');
        $this->assertSame(200, $thumbnail[0]);
        $this->assertSame(133, $thumbnail[1], 'The thumbnail keeps the 3:2 proportions of the uploaded image.');

        // Derived from the untouched original, so it is never the downscaled "medium" blown back up
        $highres = getimagesize($this->projectDir . '/public/multi-size-highres.webp');
        $this->assertSame(600, $highres[0]);
        $this->assertSame(400, $highres[1]);
    }

    // --- kept originals ------------------------------------------------------------------------------------

    // The stored file is named .webp whatever was uploaded (see UiMediaNamer), so only its bytes can say what the original is - and the copy has to be taken before processImage() overwrites them
    public function testOnPostUploadKeepsTheUntouchedOriginalOfAnEntityAskingForOne(): void
    {
        $storedPath = $this->projectDir . '/public/medias/gallery/mineraux/cailloux-a1b2c3.webp';
        mkdir(\dirname($storedPath), 0777, true);
        imagejpeg(imagecreatetruecolor(1200, 800), $storedPath);

        $media = new OriginalKeepableImageStub($storedPath, 'medias/gallery/mineraux/cailloux-a1b2c3.webp', 'private');

        $this->createListener()->onPostUpload(new Event($media, $this->createMapping()));

        // Same base name and same directory structure as the derivatives, one root over - only the extension differs, this being the one file of the set that is not a webp
        $original = $this->projectDir . '/private/medias/gallery/mineraux/cailloux-a1b2c3-original.jpg';
        $this->assertFileExists($original);
        $this->assertSame('medias/gallery/mineraux/cailloux-a1b2c3-original.jpg', $media->originalFilename);

        // What was kept is the upload, not the downscale the entity now serves in its place
        $this->assertSame(1200, getimagesize($original)[0]);
        $this->assertSame(400, getimagesize($storedPath)[0]);
    }

    public function testOnPostUploadKeepsNothingForAnEntityThatAsksForNoOriginal(): void
    {
        $storedPath = $this->projectDir . '/public/no-original.webp';
        imagejpeg(imagecreatetruecolor(1200, 800), $storedPath);

        $media = new OriginalKeepableImageStub($storedPath, 'no-original.webp', null);

        $this->createListener()->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertNull($media->originalFilename);
        $this->assertDirectoryDoesNotExist($this->projectDir . '/private');
    }

    // The extension is decided on the mime read off the file's own bytes, and a type off the allow-list is not kept at all - the one other source, the name the browser sent, is client input that would land on disk as a path
    public function testOnPostUploadKeepsNoOriginalForATypeOffTheAllowList(): void
    {
        $storedPath = $this->projectDir . '/public/logo.svg';
        file_put_contents($storedPath, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 150"></svg>');

        $media = new OriginalKeepableImageStub($storedPath, 'logo.svg', 'private');

        $this->createListener()->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertNull($media->originalFilename);
        $this->assertDirectoryDoesNotExist($this->projectDir . '/private');
    }

    // --- files that are not images -------------------------------------------------------------------------

    // This listener fires once per Vich field, and its branches answer for the entity as a whole - so a second file next to the image (a gallery media and its self-hosted video) used to be copied aside as an "original", measured, and handed to a resizer that cannot read it
    public function testOnPostUploadLeavesAFileThatIsNotAnImageAlone(): void
    {
        $storedPath = $this->projectDir . '/public/medias/gallery/kalaan/skate-a1b2c3.mp4';
        mkdir(\dirname($storedPath), 0777, true);
        // An ftyp box is what a video container starts with, and what fileinfo reads it as a video by
        file_put_contents($storedPath, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom" . str_repeat("\x00", 64));

        $media = new OriginalKeepableImageStub($storedPath, 'medias/gallery/kalaan/skate-a1b2c3.mp4', 'private');
        $sizeBefore = filesize($storedPath);

        $this->createListener()->onPostUpload(new Event($media, $this->createMapping()));

        // Not copied aside as an original, not moved, not rewritten
        $this->assertNull($media->originalFilename);
        $this->assertDirectoryDoesNotExist($this->projectDir . '/private');
        $this->assertFileExists($storedPath);
        $this->assertSame($sizeBefore, filesize($storedPath));
    }

    // The guard reads the file's own bytes, and an SVG is an image among them - it has a whole branch of the pipeline of its own (see rasterizeInPlace) that a name-based guard would have cut off
    public function testOnPostUploadStillTreatsAnSvgAsAnImage(): void
    {
        $storedPath = $this->projectDir . '/public/logo.svg';
        file_put_contents($storedPath, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 150"></svg>');

        $media = new OriginalKeepableImageStub($storedPath, 'logo.svg', 'private');

        $this->createListener()->onPostUpload(new Event($media, $this->createMapping()));

        // Reaching the pipeline is what is asserted: the original is not kept because svg is off the allow-list (see the test above), not because the guard turned it away
        $this->assertFileExists($storedPath);
        $this->assertNull($media->originalFilename);
    }

    // --- exif orientation ----------------------------------------------------------------------------------

    // GD decodes a jpeg's pixels as stored and ignores the orientation the camera recorded next to them, so a photo shot upright used to be served on its side - and every size recorded off it described the wrong one of its two dimensions
    public function testOnPostUploadTurnsAnUploadTheWayItsExifTagAsksFor(): void
    {
        $path = $this->projectDir . '/public/portrait.jpg';
        $this->writeJpegWithOrientation($path, 1200, 600, 6);

        $media = new Media();
        $media->setFilename('portrait.jpg');
        $media->setFile(new File($path));

        $this->createListener()->onPostUpload(new Event($media, $this->createMapping()));

        // Turned a quarter clockwise the upload is 600x1200, and 600 is already under the target width so nothing is resized on top - left unturned it would have come out 800x400
        $this->assertSame('600', $media->getWidth());
        $this->assertSame('1200', $media->getHeight());
    }

    // A file whose pixels an operating system already turned carries a tag reset to 1, and turning it again would lay it on its side - the very double rotation the tag exists to avoid
    public function testOnPostUploadLeavesAnUploadWhoseTagIsAlreadyNeutralAlone(): void
    {
        $path = $this->projectDir . '/public/upright.jpg';
        $this->writeJpegWithOrientation($path, 1200, 600, 1);

        $media = new Media();
        $media->setFilename('upright.jpg');
        $media->setFile(new File($path));

        $this->createListener()->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertSame('800', $media->getWidth());
        $this->assertSame('400', $media->getHeight());
    }

    // The half of the tag that moves no dimension: a 180 turn leaves an image exactly as wide as it was, so only where its pixels ended up can say whether anything happened
    public function testOnPostUploadTurnsAnUploadWhoseTagMovesNoDimension(): void
    {
        $path = $this->projectDir . '/public/upside-down.jpg';
        $this->writeJpegWithOrientation($path, 1200, 600, 3, [0, 0, 300, 150]);

        $media = new Media();
        $media->setFilename('upside-down.jpg');
        $media->setFile(new File($path));

        $this->createListener()->onPostUpload(new Event($media, $this->createMapping()));

        // The red patch was painted in the top left corner of the upload, so it has to come out in the bottom right one
        $this->assertFalse($this->hasRedAround($path, 100, 50), 'The upload was not turned at all.');
        $this->assertTrue($this->hasRedAround($path, 700, 350), 'The upload did not end up the right way round.');
    }

    // --- watermark -----------------------------------------------------------------------------------------

    // Every size a visitor can reach carries the signature, the whole set resolved from one lookup (see ImageWatermarker::prepare)
    public function testOnPostUploadStampsTheSignatureOnEveryDerivative(): void
    {
        $this->writeLogo('watermark.png');

        $path = $this->projectDir . '/public/signed.webp';
        imagepng(imagecreatetruecolor(1200, 800), $path);

        $this->createListener('watermark.png')->onPostUpload(new Event(new WatermarkedMultiSizeImageStub($path), $this->createMapping()));

        foreach (['signed.webp' => 'medium', 'signed-highres.webp' => 'highres', 'signed-thumb.webp' => 'thumbnail'] as $file => $label) {
            $derivative = $this->projectDir . '/public/' . $file;
            $this->assertTrue($this->hasRedInBottomRight($derivative), sprintf('The %s carries no signature.', $label));
            $this->assertFalse($this->hasRedInTopLeft($derivative), sprintf('The %s got signed in the wrong corner.', $label));
        }
    }

    // The reason one stamping is enough for three files: the signature is laid on the highres at a share of its width, and every smaller size is cut from it, so it comes down with the pixels instead of being laid again against a different width
    public function testTheSignatureKeepsTheSameShareOfTheWidthInEveryDerivative(): void
    {
        $this->writeLogo('watermark.png');

        $path = $this->projectDir . '/public/proportional.webp';
        imagepng(imagecreatetruecolor(1200, 800), $path);

        $this->createListener('watermark.png')->onPostUpload(new Event(new WatermarkedMultiSizeImageStub($path), $this->createMapping()));

        foreach (['proportional.webp' => 'medium', 'proportional-highres.webp' => 'highres', 'proportional-thumb.webp' => 'thumbnail'] as $file => $label) {
            $share = $this->signatureWidthShare($this->projectDir . '/public/' . $file);

            // 13.75% is what the watermarker lays, and a lossy webp bleeds the edges of a solid block either way
            $this->assertGreaterThan(0.11, $share, sprintf('The signature came out too narrow on the %s.', $label));
            $this->assertLessThan(0.17, $share, sprintf('The signature came out too wide on the %s.', $label));
        }
    }

    public function testOnPostUploadStampsNothingWhenTheEntityAsksForNoWatermark(): void
    {
        $this->writeLogo('watermark.png');

        $path = $this->projectDir . '/public/unsigned.webp';
        imagepng(imagecreatetruecolor(1200, 800), $path);

        $this->createListener('watermark.png')->onPostUpload(new Event(new WatermarkedMultiSizeImageStub($path, false), $this->createMapping()));

        $this->assertFalse($this->hasRedInBottomRight($this->projectDir . '/public/unsigned-highres.webp'));
    }

    // --- helpers -------------------------------------------------------------------------------------------

    // The watermark it is handed stamps nothing unless a signature was uploaded for one of the two site-wide roles, which is what every test but the watermarking ones wants (see ImageWatermarkerTest for the stamping itself)
    /**
     * @param array<string, string> $configs
     */
    private function createListener(?string $logo = null, array $configs = []): VichImageResizeListener
    {
        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        $repository = $this->createStub(MediaRepository::class);
        $repository->method('findOneByRole')->willReturnCallback(
            static function (string $role) use ($logo): ?Media {
                if (null === $logo || Media::ROLE_WATERMARK_ON_LIGHT !== $role) {
                    return null;
                }

                $media = new Media();
                $media->setRole($role);
                $media->setFilename($logo);

                return $media;
            }
        );

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnCallback(static fn (string $key): bool => array_key_exists($key, $configs));
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => $configs[$key] ?? null);
        $configService->method('getBool')->willReturnCallback(static fn ($value): bool => filter_var($value, \FILTER_VALIDATE_BOOLEAN));

        return new VichImageResizeListener(
            $parameterBag,
            new ImageDimensionsReader(),
            new SvgRasterizer(),
            new ImageWatermarker($configService, $repository, $parameterBag)
        );
    }

    // A jpeg carrying nothing but the orientation tag, hand-built because GD writes no EXIF at all: a little-endian TIFF block holding a single IFD entry, spliced in right after the start-of-image marker
    /**
     * @param array{0: int, 1: int, 2: int, 3: int}|null $redPatch left, top, right and bottom of a red rectangle painted before the tag is added
     */
    private function writeJpegWithOrientation(string $path, int $width, int $height, int $orientation, ?array $redPatch = null): void
    {
        $resource = imagecreatetruecolor($width, $height);
        if (null !== $redPatch) {
            imagefilledrectangle($resource, ...$redPatch, color: imagecolorallocate($resource, 255, 0, 0));
        }
        imagejpeg($resource, $path, 100);

        $tiff = 'II' . pack('v', 0x2A) . pack('V', 8)
            . pack('v', 1)
            . pack('vvV', 0x0112, 3, 1) . pack('vv', $orientation, 0)
            . pack('V', 0);

        $payload = "Exif\0\0" . $tiff;
        $jpeg = (string) file_get_contents($path);

        file_put_contents($path, substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', \strlen($payload) + 2) . $payload . substr($jpeg, 2));
    }

    private function writeLogo(string $filename): void
    {
        $resource = imagecreatetruecolor(100, 50);
        imagefilledrectangle($resource, 0, 0, 99, 49, imagecolorallocate($resource, 255, 0, 0));
        imagepng($resource, $this->projectDir . '/public/' . $filename);
    }

    // How much of the image's width the stamped signature spans, measured from the leftmost and rightmost columns holding any of its colour
    private function signatureWidthShare(string $path): float
    {
        $image = imagecreatefromstring((string) file_get_contents($path));
        $this->assertNotFalse($image, sprintf('"%s" could not be read back.', basename($path)));

        $left = null;
        $right = null;
        for ($x = 0; $x < imagesx($image); ++$x) {
            for ($y = 0; $y < imagesy($image); ++$y) {
                $color = imagecolorat($image, $x, $y);
                if (($color >> 16 & 0xFF) > 180 && ($color >> 8 & 0xFF) < 80 && ($color & 0xFF) < 80) {
                    $left ??= $x;
                    $right = $x;
                    break;
                }
            }
        }

        $this->assertNotNull($left, sprintf('"%s" carries no signature at all.', basename($path)));

        return ($right - $left + 1) / imagesx($image);
    }

    private function hasRedInBottomRight(string $path): bool
    {
        [$width, $height] = getimagesize($path);

        return $this->hasRed($path, (int) ($width / 2), (int) ($height / 2), $width, $height);
    }

    private function hasRedInTopLeft(string $path): bool
    {
        [$width, $height] = getimagesize($path);

        return $this->hasRed($path, 0, 0, (int) ($width / 2), (int) ($height / 2));
    }

    private function hasRedAround(string $path, int $x, int $y): bool
    {
        return $this->hasRed($path, $x - 20, $y - 20, $x + 20, $y + 20);
    }

    // Read through imagecreatefromstring(), which sniffs the format off the bytes: the stored file keeps the name it was uploaded under, whatever the pipeline wrote into it. Loose on the channels rather than exact, everything here having been through a lossy encoder
    private function hasRed(string $path, int $fromX, int $fromY, int $toX, int $toY): bool
    {
        $image = imagecreatefromstring((string) file_get_contents($path));
        $this->assertNotFalse($image, sprintf('"%s" could not be read back.', basename($path)));

        for ($x = max(0, $fromX); $x < min(imagesx($image), $toX); ++$x) {
            for ($y = max(0, $fromY); $y < min(imagesy($image), $toY); ++$y) {
                $color = imagecolorat($image, $x, $y);
                if (($color >> 16 & 0xFF) > 180 && ($color >> 8 & 0xFF) < 80 && ($color & 0xFF) < 80) {
                    return true;
                }
            }
        }

        return false;
    }
}

// The two contracts a photo combines: several sizes, each signed (GalleryBundle's GalleryMedia is the first entity to do both)
class WatermarkedMultiSizeImageStub implements VichMultiSizeImageInterface, VichWatermarkableInterface
{
    public function __construct(
        private readonly string $path,
        private readonly bool $wants = true,
    ) {
    }

    public function getImageWidth(): int
    {
        return 400;
    }

    public function getThumbnailSize(): int
    {
        return 200;
    }

    public function getHighresWidth(): int
    {
        return 600;
    }

    public function getFilename(): string
    {
        return basename($this->path);
    }

    public function getFile(): File
    {
        return new File($this->path);
    }

    public function wantsWatermark(): bool
    {
        return $this->wants;
    }

    public function getWatermarkPosition(): ?string
    {
        return null;
    }
}

// Nothing in this bundle keeps its originals either (GalleryBundle's GalleryMedia is the first consumer), so the copy needs an entity of its own to be tested at all
class OriginalKeepableImageStub implements VichMultiSizeImageInterface, VichOriginalKeepableInterface
{
    public ?string $originalFilename = null;

    public function __construct(
        private readonly string $path,
        private readonly string $filename,
        private readonly ?string $originalDirectory,
    ) {
    }

    public function getImageWidth(): int
    {
        return 400;
    }

    public function getThumbnailSize(): int
    {
        return 200;
    }

    public function getHighresWidth(): int
    {
        return 600;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getFile(): File
    {
        return new File($this->path);
    }

    public function getOriginalDirectory(): ?string
    {
        return $this->originalDirectory;
    }

    public function setOriginalFilename(?string $filename): void
    {
        $this->originalFilename = $filename;
    }
}

// Nothing in this bundle implements VichMultiSizeImageInterface (its consumers are satellite bundles, e.g. GalleryBundle's GalleryMedia), so the derivatives it opts into need an entity of their own to be tested at all
class MultiSizeImageStub implements VichMultiSizeImageInterface
{
    public function __construct(private readonly string $path)
    {
    }

    public function getImageWidth(): int
    {
        return 400;
    }

    public function getThumbnailSize(): int
    {
        return 200;
    }

    public function getHighresWidth(): int
    {
        return 600;
    }

    public function getFilename(): string
    {
        return basename($this->path);
    }

    public function getFile(): File
    {
        return new File($this->path);
    }
}
