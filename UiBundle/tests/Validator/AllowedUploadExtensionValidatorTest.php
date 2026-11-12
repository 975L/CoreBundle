<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Validator;

use c975L\UiBundle\Validator\AllowedUploadExtension;
use c975L\UiBundle\Validator\AllowedUploadExtensionValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;

// The door in front of UiMediaNamer: the client's extension in lower case, against the list both of them share
class AllowedUploadExtensionValidatorTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = (string) tempnam(sys_get_temp_dir(), 'allowed-extension-test');
        file_put_contents($this->path, 'content');
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    // The whole validator, through a real validation so the translation domain and the message are the ones a form shows
    private function violations(string $clientName): array
    {
        return iterator_to_array(Validation::createValidator()->validate(new UploadedFile($this->path, $clientName, test: true), new AllowedUploadExtension()));
    }

    #[DataProvider('refused')]
    public function testRefused(string $clientName): void
    {
        $violations = $this->violations($clientName);

        $this->assertCount(1, $violations);
        $this->assertSame('label.upload_extension_not_allowed', $violations[0]->getMessageTemplate());
    }

    /** @return iterable<array{string}> */
    public static function refused(): iterable
    {
        foreach (['x.php', 'x.PHP', 'x.jpg.php', 'x.phtml', 'x.phar', 'x.html', 'x.htm', 'x.shtml', 'x.xhtml', 'x.xml', 'x.js', 'x.mjs', 'x.gpx', 'x.kml', '.htaccess', 'x.ini', 'x.sh', 'x.avif', 'x.AVIF'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('accepted')]
    public function testAccepted(string $clientName): void
    {
        $this->assertSame([], $this->violations($clientName));
    }

    /** @return iterable<array{string}> */
    public static function accepted(): iterable
    {
        foreach (['x.jpg', 'X.JPG', 'x.svg', 'x.pdf', 'x.mp3', 'x.mp4', 'x.woff2', 'x.vtt', 'x.epub', 'x.zip', 'x.heic', 'x.jfif', 'x.m4b', 'x.mkv', 'x.7z', 'archive.tar.gz', 'noextension'] as $name) {
            yield $name => [$name];
        }
    }

    // An avif is refused by its bytes too, whatever name the phone or the editor gave it, and the message names what it really is
    public function testAnAvifIsRefusedWhateverItIsNamed(): void
    {
        file_put_contents($this->path, "\x00\x00\x00\x1cftypavif\x00\x00\x00\x00avifmif1miaf");

        foreach (['photo.jpg', 'noextension'] as $name) {
            $violations = $this->violations($name);

            $this->assertCount(1, $violations, $name);
            $this->assertSame('avif', $violations[0]->getParameters()['%extension%']);
        }
        $this->assertTrue(AllowedUploadExtensionValidator::hasRefusedType(new File($this->path)));
    }

    // An import reaches no form, so it asks the bytes the same question: anything else is no refused type
    public function testOtherBytesAreNoRefusedType(): void
    {
        $this->assertFalse(AllowedUploadExtensionValidator::hasRefusedType(new File($this->path)));
    }

    // Nothing was uploaded: the field stays optional
    public function testNullIsLeftAlone(): void
    {
        $this->assertCount(0, Validation::createValidator()->validate(null, new AllowedUploadExtension()));
    }

    public function testTheListNeverHoldsWhatAServerOrABrowserRuns(): void
    {
        foreach (['php', 'php5', 'phtml', 'phar', 'html', 'htm', 'shtml', 'xhtml', 'xml', 'js', 'mjs', 'gpx', 'kml', 'htaccess', 'ini', 'sh'] as $extension) {
            $this->assertNotContains($extension, AllowedUploadExtension::EXTENSIONS);
        }
    }
}
