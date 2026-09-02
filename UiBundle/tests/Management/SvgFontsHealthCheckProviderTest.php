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
use c975L\UiBundle\Contract\MediaUsageProviderInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Management\SvgFontsHealthCheckProvider;
use c975L\UiBundle\Registry\MediaUsageRegistry;
use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Service\SvgTextDetector;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class SvgFontsHealthCheckProviderTest extends TestCase
{
    private const string VECTORIZED = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0 L1 1 Z"/></svg>';
    private const string WITH_TEXT = '<svg xmlns="http://www.w3.org/2000/svg"><text font-family="Riffic Free">975L</text></svg>';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/svg-fonts-health-check-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    /**
     * @param array<string, ?string> $files              filename => contents, null for a row whose file is missing
     * @param ?MediaUsageRegistry    $mediaUsageRegistry the usages a media is known through, only given by the test checking the ones in the bin are left out
     */
    private function createProvider(array $files, ?MediaUsageRegistry $mediaUsageRegistry = null): SvgFontsHealthCheckProvider
    {
        $media = [];
        foreach ($files as $filename => $contents) {
            if (null !== $contents) {
                file_put_contents($this->projectDir . '/public/' . $filename, $contents);
            }

            $row = new Media();
            $row->setFilename($filename);
            // The binned-only check reads ids, which only a persisted row would carry
            new \ReflectionProperty(Media::class, 'id')->setValue($row, \count($media) + 1);
            $media[] = $row;
        }

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('findSvgCandidates')->willReturn($media);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('https://example.com');

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/media');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []) => $id . '|' . implode('', $params)
        );

        return new SvgFontsHealthCheckProvider(
            $mediaRepository,
            new SvgTextDetector(),
            $mediaUsageRegistry ?? new MediaUsageRegistry(),
            $configService,
            $adminUrlGenerator,
            $translator,
            $this->projectDir,
        );
    }

    public function testGetKind(): void
    {
        $this->assertSame('svg-fonts', $this->createProvider([])->getKind());
    }

    public function testAnSvgDrawingTextIsAWarningNamingItsFont(): void
    {
        $rows = $this->createProvider(['logo.svg' => self::WITH_TEXT])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
        $this->assertSame('https://example.com/logo.svg', $rows[0]['url']);
        $this->assertStringContainsString('(Riffic Free)', $rows[0]['summary']);
        $this->assertSame(['fonts' => ['Riffic Free']], $rows[0]['details']);
    }

    // The OK row is what lets a fixed file go back to green: results are kept per url and kind, so dropping it would leave the old warning standing forever
    public function testAVectorizedSvgStillGetsItsRow(): void
    {
        $rows = $this->createProvider(['logo.svg' => self::VECTORIZED])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
    }

    // An icon role is rasterized on upload, so its stored file is no longer SVG markup - nothing to say about it here
    public function testARowWhoseStoredFileIsNoLongerSvgIsSkipped(): void
    {
        $rows = $this->createProvider(['favicon.ico' => "\x00\x00\x01\x00"])->runChecks();

        $this->assertSame([], $rows);
    }

    public function testARowWhoseFileIsMissingIsSkipped(): void
    {
        $rows = $this->createProvider(['gone.svg' => null])->runChecks();

        $this->assertSame([], $rows);
    }

    public function testEveryCandidateGetsItsOwnRow(): void
    {
        $rows = $this->createProvider([
            'logo.svg' => self::WITH_TEXT,
            'icon.svg' => self::VECTORIZED,
        ])->runChecks();

        $this->assertCount(2, $rows);
        $this->assertSame(
            [HealthCheckResult::STATUS_WARNING, HealthCheckResult::STATUS_OK],
            array_column($rows, 'status'),
        );
    }

    // A file only a binned page still draws is nothing anyone has to fix, and the exhaustive purge retires the row it had (see MediaUsageProviderInterface)
    public function testAMediaOnlyUsedByABinnedOwnerIsSkipped(): void
    {
        $usageProvider = $this->createStub(MediaUsageProviderInterface::class);
        $usageProvider->method('getUsages')->willReturn([
            1 => [['label' => 'binned page', 'url' => null, 'binned' => true]],
        ]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($usageProvider);

        $provider = $this->createProvider(['drawn.svg' => self::WITH_TEXT], $registry);

        $this->assertSame([], $provider->runChecks());
    }
}
