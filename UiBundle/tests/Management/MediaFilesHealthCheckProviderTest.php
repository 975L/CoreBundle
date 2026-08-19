<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\MediaCrudController;
use c975L\UiBundle\Controller\Management\SiteGraphicCrudController;
use c975L\UiBundle\Entity\Font;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Management\MediaFilesHealthCheckProvider;
use c975L\UiBundle\Repository\FontRepository;
use c975L\UiBundle\Repository\MediaRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class MediaFilesHealthCheckProviderTest extends TestCase
{
    private string $projectDir;

    // The controller each generated edit url was asked for, so the test can tell the media library's screen from the site graphics one
    private array $controllers = [];

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/media-files-health-check-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    /**
     * @param array<int, array{0: Media|Font, 1: bool}> $rows the row and whether its file sits on disk
     */
    private function createProvider(array $rows): MediaFilesHealthCheckProvider
    {
        $medias = [];
        $fonts = [];
        foreach ($rows as [$row, $onDisk]) {
            if ($onDisk) {
                $path = $this->projectDir . '/public/' . $row->getFilename();
                new Filesystem()->mkdir(\dirname($path));
                file_put_contents($path, 'file');
            }

            if ($row instanceof Font) {
                $fonts[] = $row;

                continue;
            }

            $medias[] = $row;
        }

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('findWithFilename')->willReturn($medias);

        $fontRepository = $this->createStub(FontRepository::class);
        $fontRepository->method('findWithFilename')->willReturn($fonts);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('https://example.com/');

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnCallback(function (string $controller) use ($adminUrlGenerator): AdminUrlGeneratorInterface {
            $this->controllers[] = $controller;

            return $adminUrlGenerator;
        });
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/media/edit');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []) => $id . '|' . implode('', $params)
        );

        return new MediaFilesHealthCheckProvider(
            $mediaRepository,
            $fontRepository,
            $adminUrlGenerator,
            $configService,
            $translator,
            $this->projectDir,
        );
    }

    private function createMedia(string $filename, ?string $role = null, ?string $name = null, int $id = 1): Media
    {
        $media = new Media()->setFilename($filename)->setRole($role)->setName($name);
        new \ReflectionProperty(Media::class, 'id')->setValue($media, $id);

        return $media;
    }

    public function testGetKind(): void
    {
        $this->assertSame('files-ui', $this->createProvider([])->getKind());
    }

    public function testASiteDeclaringNoFileReportsNothing(): void
    {
        $this->assertSame([], $this->createProvider([])->runChecks());
    }

    // The case this check exists for: the row still declares the file, every screen still lists it, and only the page it belongs to shows the hole
    public function testADeclaredFileMissingFromTheServerIsAnError(): void
    {
        $rows = $this->createProvider([[$this->createMedia('watermark-on-dark.webp', Media::ROLE_WATERMARK_ON_DARK), false]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('https://example.com/watermark-on-dark.webp', $rows[0]['url']);
        $this->assertStringContainsString('label.health_check_declared_file_missing', $rows[0]['summary']);
    }

    // The OK row is what lets a re-uploaded file go back to green: results are kept per url and kind, so dropping it would leave the old error standing forever
    public function testAFileInPlaceStillGetsItsRow(): void
    {
        $rows = $this->createProvider([[$this->createMedia('medias/site/logo.webp', name: 'Logo'), true]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
        $this->assertSame('Logo', $rows[0]['label']);
    }

    // A file gone from a font leaves the site rendering its fallback, which no error anywhere reports either
    public function testAFontFileIsCheckedToo(): void
    {
        $font = new Font()->setName('Inter')->setFilename('medias/fonts/inter.woff2');
        $rows = $this->createProvider([[$font, false]])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $rows[0]['status']);
        $this->assertSame('Inter', $rows[0]['label']);
    }

    // The two are the same entity behind two CRUDs, and the link has to open the screen the file is re-uploaded from
    public function testASiteGraphicLinksToItsOwnScreenRatherThanToTheMediaLibrary(): void
    {
        $this->createProvider([
            [$this->createMedia('favicon.ico', Media::ROLE_FAVICON), true],
            [$this->createMedia('medias/site/block-article-2.webp', name: 'Article', id: 2), true],
        ])->runChecks();

        $this->assertSame([SiteGraphicCrudController::class, MediaCrudController::class], $this->controllers);
    }

    // A role names a site graphic on its own screen, an admin-typed name being asked for block medias only
    public function testAGraphicWithNoNameIsLabelledByItsRole(): void
    {
        $rows = $this->createProvider([[$this->createMedia('watermark-on-light.webp', Media::ROLE_WATERMARK_ON_LIGHT), true]])->runChecks();

        $this->assertSame(Media::ROLE_WATERMARK_ON_LIGHT, $rows[0]['label']);
    }

    // Nothing names it and nothing looks for it: a media created for its caption alone, or one whose upload never landed
    public function testARowNamingNoFileIsSkipped(): void
    {
        $rows = $this->createProvider([
            [$this->createMedia(''), false],
            [$this->createMedia('medias/site/kept.webp', name: 'Kept', id: 2), true],
        ])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame('https://example.com/medias/site/kept.webp', $rows[0]['url']);
    }
}
