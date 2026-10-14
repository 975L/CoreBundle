<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Controller\AppUpdateController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// The app is sent the latest update of its own runtime version, as the very bytes that were signed, or told there is nothing new, or sent back to the code it was built with
class AppUpdateControllerTest extends TestCase
{
    private const string UPDATE_ID = '0f1e2d3c-4b5a-4968-8776-655443322110';

    private const string MANIFEST = '{"id":"0f1e2d3c-4b5a-4968-8776-655443322110","runtimeVersion":"1.0.0"}';

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/app-updates-' . bin2hex(random_bytes(4));
        $platform = $this->directory . '/1.0.0/android';

        $filesystem = new Filesystem();
        $filesystem->dumpFile($platform . '/latest', self::UPDATE_ID . "\n");
        $filesystem->dumpFile($platform . '/' . self::UPDATE_ID . '/manifest.json', self::MANIFEST);
        $filesystem->dumpFile($platform . '/' . self::UPDATE_ID . '/manifest.sig', 'sig="c2lnbmVk", keyid="main"');
        $filesystem->dumpFile($platform . '/no-update.json', '{"type":"noUpdateAvailable"}');
        $filesystem->dumpFile($platform . '/rollback.json', '{"type":"rollBackToEmbedded","parameters":{"commitTime":"2026-10-14T10:00:00.000Z"}}');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    private function createController(): AppUpdateController
    {
        $controller = new AppUpdateController($this->directory);
        $controller->setContainer(new Container());

        return $controller;
    }

    /** @param array<string, string> $headers */
    private function request(array $headers = []): Request
    {
        $request = Request::create('/app/updates');
        $request->headers->add($headers + [
            'expo-platform' => 'android',
            'expo-runtime-version' => '1.0.0',
            'expo-protocol-version' => '1',
        ]);

        return $request;
    }

    // The one part of the multipart answer: its headers and its body
    /** @return array{0: string, 1: string} */
    private function part(Response $response): array
    {
        preg_match('/boundary=(\w+)/', (string) $response->headers->get('Content-Type'), $matches);
        $part = explode("\r\n--" . $matches[1] . '--', explode('--' . $matches[1] . "\r\n", (string) $response->getContent(), 2)[1])[0];

        return explode("\r\n\r\n", $part, 2);
    }

    // The manifest goes out byte for byte as it was signed, with its signature
    public function testSendsTheLatestManifestAsSigned(): void
    {
        $response = $this->createController()->manifest($this->request());
        [$headers, $body] = $this->part($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('1', $response->headers->get('expo-protocol-version'));
        $this->assertSame('0', $response->headers->get('expo-sfv-version'));
        $this->assertStringContainsString('name="manifest"', $headers);
        $this->assertStringContainsString('expo-signature: sig="c2lnbmVk", keyid="main"', $headers);
        $this->assertSame(self::MANIFEST, $body);
    }

    // An app already running the latest update hears there is nothing new
    public function testAnAppUpToDateIsToldThereIsNothingNew(): void
    {
        [$headers, $body] = $this->part($this->createController()->manifest($this->request(['expo-current-update-id' => strtoupper(self::UPDATE_ID)])));

        $this->assertStringContainsString('name="directive"', $headers);
        $this->assertSame('{"type":"noUpdateAvailable"}', $body);
    }

    // A rollback sends the apps back to the code they were built with, except those already running it
    public function testARollbackSendsTheAppsBackToTheirOwnCode(): void
    {
        file_put_contents($this->directory . '/1.0.0/android/latest', 'rollback');

        $this->assertStringContainsString('rollBackToEmbedded', $this->part($this->createController()->manifest($this->request(['expo-current-update-id' => 'a', 'expo-embedded-update-id' => 'b'])))[1]);
        $this->assertStringContainsString('noUpdateAvailable', $this->part($this->createController()->manifest($this->request(['expo-current-update-id' => 'b', 'expo-embedded-update-id' => 'b'])))[1]);
    }

    // A runtime version nothing was published for, as on a site with no app
    public function testARuntimeVersionWithNoUpdateIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->manifest($this->request(['expo-runtime-version' => '2.0.0']));
    }

    // The runtime version names a folder: nothing that climbs out of it
    public function testARuntimeVersionClimbingOutIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController()->manifest($this->request(['expo-runtime-version' => '../..']));
    }

    // A "latest" naming no update is no update
    public function testALatestNamingNoUpdateIsNotFound(): void
    {
        file_put_contents($this->directory . '/1.0.0/android/latest', '../../secret');

        $this->expectException(NotFoundHttpException::class);

        $this->createController()->manifest($this->request());
    }

    // Another version of the protocol is not spoken here
    public function testAnotherProtocolVersionIsNotAcceptable(): void
    {
        $this->assertSame(406, $this->createController()->manifest($this->request(['expo-protocol-version' => '0']))->getStatusCode());
    }
}
