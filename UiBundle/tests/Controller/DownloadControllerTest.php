<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Controller\DownloadController;
use c975L\UiBundle\Tests\Controller\Management\ControllerContainerTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class DownloadControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    // The first bytes of a real jpeg: the asset route reads what a file holds, not what it is named
    private const string JPEG = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00";

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/download-controller-test-' . uniqid();
        mkdir($this->projectDir . '/public/medias', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function createController(): DownloadController
    {
        $controller = new DownloadController();
        $parameterBag = new ParameterBag(['kernel.project_dir' => $this->projectDir]);
        $controller->setContainer($this->createContainer(['parameter_bag' => new ContainerBag($this->buildContainerWith($parameterBag))]));

        return $controller;
    }

    // ContainerBag needs a Container to read its parameters from
    private function buildContainerWith(ParameterBag $parameterBag): \Symfony\Component\DependencyInjection\Container
    {
        return new \Symfony\Component\DependencyInjection\Container($parameterBag);
    }

    // The file is already served publicly - all this adds is the Content-Disposition making the browser save it
    public function testDownloadFileForcesTheAttachmentDisposition(): void
    {
        file_put_contents($this->projectDir . '/public/medias/brochure.pdf', '%PDF-1.4');

        $response = $this->createController()->downloadFile('medias/brochure.pdf');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertStringStartsWith(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $response->headers->get('Content-Disposition')
        );
        $this->assertStringContainsString('brochure.pdf', $response->headers->get('Content-Disposition'));
    }

    // The other half of the pair: the same file, opened in the browser instead of saved - what a scanned deed or a pdf book is looked at through
    public function testAssetFileServesTheFileInlineAndKeepsItPrivate(): void
    {
        file_put_contents($this->projectDir . '/public/medias/acte.jpg', self::JPEG);

        $response = $this->createController()->assetFile('medias/acte.jpg');

        $this->assertStringStartsWith(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $response->headers->get('Content-Disposition')
        );
        // Private, and carrying the header that keeps AbstractSessionListener from taking the hour back to zero on a request with a session
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertSame(3600, $response->getMaxAge());
        $this->assertTrue($response->headers->has(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    // A download is offered on a page anybody reads: marking it private would only stop a shared cache from doing its work
    public function testDownloadFileIsNotMarkedPrivate(): void
    {
        file_put_contents($this->projectDir . '/public/medias/brochure.pdf', '%PDF-1.4');

        $response = $this->createController()->downloadFile('medias/brochure.pdf');

        $this->assertFalse($response->headers->hasCacheControlDirective('private'));
    }

    // The asset route takes any file name - spaces, accents, parentheses, whatever the scanner wrote - so it is the action itself that refuses to climb out of public/
    public function testAssetFileRefusesToClimbOutOfPublic(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->assetFile('medias/../../.env');
    }

    // A file the web server itself refuses to serve - an Apache rule, a PHP source, a PHP ini - is no business of a route opening scans and photographs
    #[DataProvider('unviewableFiles')]
    public function testAssetFileRefusesWhatIsNeitherAMediaNorAPdf(string $name, string $content): void
    {
        file_put_contents($this->projectDir . '/public/' . $name, $content);

        $this->expectException(NotFoundHttpException::class);

        $this->createController()->assetFile($name);
    }

    // What a site's public/ really holds beside its medias
    public static function unviewableFiles(): iterable
    {
        yield 'an Apache rule' => ['.htaccess', "RewriteEngine On\nRewriteRule ^ index.php [L]\n"];
        yield 'a PHP source' => ['index.php', "<?php\n\nrequire dirname(__DIR__) . '/vendor/autoload.php';\n"];
        yield 'a PHP ini' => ['.user.ini', "memory_limit = 512M\n"];
    }

    // A pdf book is exactly what the route exists to open
    public function testAssetFileOpensAPdf(): void
    {
        file_put_contents($this->projectDir . '/public/medias/livre.pdf', "%PDF-1.4\n");

        $response = $this->createController()->assetFile('medias/livre.pdf');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
    }

    // A directory mounted elsewhere and symlinked under public/ still answers: the guard reads the path as it was asked for, not the one the link resolves to
    public function testAssetFileFollowsASymlinkedDirectory(): void
    {
        mkdir($this->projectDir . '/elsewhere', 0777, true);
        file_put_contents($this->projectDir . '/elsewhere/photo.jpg', self::JPEG);
        symlink($this->projectDir . '/elsewhere', $this->projectDir . '/public/photos');

        $response = $this->createController()->assetFile('photos/photo.jpg');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
    }

    public function testDownloadFileThrowsNotFoundForAMissingFile(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->downloadFile('medias/nothing-here.pdf');
    }

    // The route's own requirement is what keeps a traversal out - the action itself concatenates the path as given
    public function testTheRouteRequirementRejectsTraversalAndAllowsARealPath(): void
    {
        $route = new \ReflectionMethod(DownloadController::class, 'downloadFile')
            ->getAttributes(Route::class)[0]
            ->newInstance();

        $pattern = '#^' . $route->requirements['file'] . '$#u';

        $this->assertSame(1, preg_match($pattern, 'medias/brochure.pdf'));
        $this->assertSame(1, preg_match($pattern, 'medias/sous-dossier/fichier_2.pdf'));
        $this->assertSame(0, preg_match($pattern, '../../.env'));
        $this->assertSame(0, preg_match($pattern, 'medias/../../config/packages/security.yaml'));
    }
}
