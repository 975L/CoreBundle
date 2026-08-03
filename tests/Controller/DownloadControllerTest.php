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
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class DownloadControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

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

    public function testDownloadFileThrowsNotFoundForAMissingFile(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->downloadFile('medias/nothing-here.pdf');
    }

    // The route's own requirement is what keeps a traversal out - the action itself concatenates the path as given
    public function testTheRouteRequirementRejectsTraversalAndAllowsARealPath(): void
    {
        $route = (new \ReflectionMethod(DownloadController::class, 'downloadFile'))
            ->getAttributes(Route::class)[0]
            ->newInstance();

        $pattern = '#^' . $route->requirements['file'] . '$#u';

        $this->assertSame(1, preg_match($pattern, 'medias/brochure.pdf'));
        $this->assertSame(1, preg_match($pattern, 'medias/sous-dossier/fichier_2.pdf'));
        $this->assertSame(0, preg_match($pattern, '../../.env'));
        $this->assertSame(0, preg_match($pattern, 'medias/../../config/packages/security.yaml'));
    }
}
