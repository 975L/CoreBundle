<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Validator;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\SvgRasterizer;
use c975L\UiBundle\Validator\FixedIconFormat;
use c975L\UiBundle\Validator\FixedIconFormatValidator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class FixedIconFormatValidatorTest extends ConstraintValidatorTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/fixed-icon-format-test-' . uniqid();
        mkdir($this->directory, 0777, true);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory . '/*'));
        rmdir($this->directory);

        parent::tearDown();
    }

    protected function createValidator(): FixedIconFormatValidator
    {
        return new FixedIconFormatValidator(new SvgRasterizer());
    }

    private function createMedia(?string $role, string $name, string $content): Media
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $content);

        $media = new Media();
        $media->setRole($role);
        $media->setFile(new File($path));

        return $media;
    }

    private function createPng(): string
    {
        ob_start();
        imagepng(imagecreatetruecolor(200, 200));

        return (string) ob_get_clean();
    }

    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="45" fill="#e63946"/></svg>';

    public function testNonMediaValueIsIgnored(): void
    {
        $this->validator->validate(new \stdClass(), new FixedIconFormat());

        $this->assertNoViolation();
    }

    // Only favicon and apple-touch-icon are stored under a format of their own - every other media keeps whatever was uploaded, SVG logo included
    public function testARoleWithoutAFixedIconSpecAcceptsAnything(): void
    {
        $this->validator->validate($this->createMedia(Media::ROLE_LOGO, 'logo.svg', self::SVG), new FixedIconFormat());

        $this->assertNoViolation();
    }

    public function testARasterUploadIsAccepted(): void
    {
        $this->validator->validate($this->createMedia(Media::ROLE_FAVICON, 'favicon.png', $this->createPng()), new FixedIconFormat());

        $this->assertNoViolation();
    }

    // A PDF has no business being a favicon, and nothing downstream could convert it
    public function testAnUnrelatedFormatIsRefused(): void
    {
        $this->validator->validate($this->createMedia(Media::ROLE_FAVICON, 'favicon.pdf', '%PDF-1.4 not really'), new FixedIconFormat());

        $this->buildViolation('label.fixed_icon_invalid_format')
            ->setParameter('%formats%', SvgRasterizer::isSupported() ? 'PNG, JPG, GIF, WEBP, SVG' : 'PNG, JPG, GIF, WEBP')
            ->atPath('property.path.file')
            ->assertRaised();
    }

    // The point of the whole SVG support: accepted only once actually rendered, so an upload the server can't convert is refused at the door instead of ending up stored as SVG markup under an .ico name
    public function testAnSvgIsAcceptedOnlyWhenItCanBeRasterized(): void
    {
        $this->validator->validate($this->createMedia(Media::ROLE_FAVICON, 'favicon.svg', self::SVG), new FixedIconFormat());

        if (SvgRasterizer::isSupported()) {
            $this->assertNoViolation();

            return;
        }

        $this->buildViolation('label.fixed_icon_invalid_format')
            ->setParameter('%formats%', 'PNG, JPG, GIF, WEBP')
            ->atPath('property.path.file')
            ->assertRaised();
    }

    // Malformed markup renders to nothing - it must be caught here, the conversion happening long after the row is stored
    public function testAnUnrenderableSvgIsRefused(): void
    {
        $media = $this->createMedia(Media::ROLE_APPLE_TOUCH_ICON, 'apple-touch-icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="50"');

        $this->validator->validate($media, new FixedIconFormat());

        $this->buildViolation('label.fixed_icon_invalid_format')
            ->setParameter('%formats%', SvgRasterizer::isSupported() ? 'PNG, JPG, GIF, WEBP, SVG' : 'PNG, JPG, GIF, WEBP')
            ->atPath('property.path.file')
            ->assertRaised();
    }
}
