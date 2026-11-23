<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Mcp;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\MediaUsageProviderInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Mcp\UiMcpProvider;
use c975L\UiBundle\Registry\MediaUsageRegistry;
use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Tests\Fixtures\DummyUser;
use c975L\UiBundle\Twig\MediaUrlExtension;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Validation;

class UiMcpProviderTest extends TestCase
{
    // The tools named under the "ui" key, only the edit writing, none destroying
    public function testTools(): void
    {
        $tools = iterator_to_array($this->provider()->getMcpTools(), false);

        $this->assertSame('ui', $this->provider()->getMcpKey());
        $this->assertSame(['ui_media_list', 'ui_media_get', 'ui_media_edit'], array_map(static fn ($tool): string => $tool->name, $tools));
        $this->assertSame([false, false, true], array_map(static fn ($tool): bool => $tool->writes, $tools));
        $this->assertSame([false, false, false], array_map(static fn ($tool): bool => $tool->destroys, $tools));
    }

    // The medias the repository finds, each with its url and where it is used, the limit kept within its bounds
    public function testList(): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects($this->once())->method('findForAgents')->with('item', true, UiMcpProvider::MAX_LIMIT)->willReturn([$this->media(2, 'item-2.webp'), $this->media(1, 'item-1.pdf')]);

        $list = $this->provider($repository)->list('item', 500, true);

        $this->assertSame([2, 1], array_column($list, 'id'));
        $this->assertSame(['/medias/item-2.webp', '/medias/item-1.pdf'], array_column($list, 'url'));
        $this->assertSame(['catalog'], $list[0]['usedIn']);
        $this->assertSame(['image/webp', 1024, '800', '600', 'alt-2', 'caption-2', null], [$list[0]['type'], $list[0]['size'], $list[0]['width'], $list[0]['height'], $list[0]['alt'], $list[0]['caption'], $list[0]['role']]);
    }

    // A media without a file has no url to give
    public function testListGivesNoUrlWithoutAFile(): void
    {
        $repository = $this->createStub(MediaRepository::class);
        $repository->method('findForAgents')->willReturn([$this->media(3, null)]);

        $this->assertNull($this->provider($repository)->list()[0]['url']);
    }

    // A media read with its credits and switches
    public function testGet(): void
    {
        $media = $this->media(2, 'item-2.webp')->setCredits('credits-1')->setName('item-2');

        $read = $this->provider($this->holding($media))->get(2);

        $this->assertSame(['item-2.webp', 'item-2', 'credits-1', false, false, ['catalog']], [$read['file'], $read['name'], $read['credits'], $read['rightsReserved'], $read['membersOnly'], $read['usedIn']]);
    }

    // The uploader's account is never told, by any of the reading tools
    public function testOutputHoldsNoPersonalData(): void
    {
        $media = $this->media(2, 'item-2.webp')->setUser(new DummyUser());
        $repository = $this->holding($media);
        $repository->method('findForAgents')->willReturn([$media]);

        $output = (string) json_encode([$this->provider($repository)->list(), $this->provider($repository)->get(2)]);

        $this->assertStringNotContainsString('user@example.test', $output);
        $this->assertStringNotContainsString('"user"', $output);
    }

    // The alt text and caption given written trimmed, an empty one cleared, the flush done once
    public function testEdit(): void
    {
        $media = $this->media(2, 'item-2.webp');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $edited = $this->provider($this->holding($media), $entityManager)->edit(2, ' alt-3 ', '');

        $this->assertSame(['id' => 2, 'alt' => 'alt-3', 'caption' => null], $edited);
    }

    // Only the text given is changed
    public function testEditKeepsWhatIsNotGiven(): void
    {
        $media = $this->media(2, 'item-2.webp');

        $this->provider($this->holding($media))->edit(2, caption: 'caption-3');

        $this->assertSame(['alt-2', 'caption-3'], [$media->getAlt(), $media->getLabel()]);
    }

    // A text longer than its column is refused by the entity's own constraint, and nothing is written
    public function testEditRefusesATextTooLong(): void
    {
        $media = $this->media(2, 'item-2.webp');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        try {
            $this->provider($this->holding($media), $entityManager)->edit(2, 'alt-3', str_repeat('a', 256));
            $this->fail('A caption of 256 characters was accepted.');
        } catch (ToolCallException $exception) {
            $this->assertStringStartsWith('label: ', $exception->getMessage());
        }

        $this->assertSame(['alt-2', 'caption-2'], [$media->getAlt(), $media->getLabel()]);
    }

    // A site graphic is left to its own screen, as the library leaves it
    public function testEditRefusesASiteGraphic(): void
    {
        $provider = $this->provider($this->holding($this->media(2, 'logo.png')->setRole(Media::ROLE_LOGO)));

        $this->assertStringContainsString('site graphic', $this->refusal(static fn () => $provider->edit(2, 'alt-3')));
    }

    // An unknown id is refused by the tools reading one media
    #[DataProvider('oneMediaTools')]
    public function testUnknownMedia(string $tool): void
    {
        $this->assertSame('No media 9.', $this->call($this->provider(), $tool));
    }

    // An account without the editor role is refused by every tool, before any id is looked up
    #[DataProvider('everyTool')]
    public function testRefusedWithoutTheRole(string $tool): void
    {
        $repository = $this->createMock(MediaRepository::class);
        $repository->expects($this->never())->method('find');
        $repository->expects($this->never())->method('findForAgents');

        $this->assertSame('This account may not work on the media library.', $this->call($this->provider($repository, granted: false), $tool));
    }

    /** @return iterable<array{string}> */
    public static function oneMediaTools(): iterable
    {
        yield ['get'];
        yield ['edit'];
    }

    /** @return iterable<array{string}> */
    public static function everyTool(): iterable
    {
        yield ['list'];
        yield from self::oneMediaTools();
    }

    // The refusal of one tool called on the media 9
    private function call(UiMcpProvider $provider, string $tool): string
    {
        return $this->refusal(static fn () => match ($tool) {
            'list' => $provider->list(),
            'get' => $provider->get(9),
            default => $provider->edit(9, 'alt-3'),
        });
    }

    // The message of the refusal a call ends on, the test failed when it ends on none
    private function refusal(callable $call): string
    {
        try {
            $call();
        } catch (ToolCallException $exception) {
            return $exception->getMessage();
        }

        $this->fail('The call was not refused.');
    }

    // A repository finding the media given by its id
    private function holding(Media $media): MediaRepository & Stub
    {
        $repository = $this->createStub(MediaRepository::class);
        $repository->method('find')->willReturn($media);

        return $repository;
    }

    // A media with its id set as Doctrine would
    private function media(int $id, ?string $filename): Media
    {
        $media = new Media()
            ->setFilename($filename)
            ->setMimeType('image/webp')
            ->setSize(1024)
            ->setWidth('800')
            ->setHeight('600')
            ->setAlt('alt-' . $id)
            ->setLabel('caption-' . $id);
        new \ReflectionProperty(Media::class, 'id')->setValue($media, $id);

        return $media;
    }

    // The provider over the services given, a real validator reading the entity's constraints and a registry saying every media is used in the catalog
    private function provider(?MediaRepository $repository = null, ?EntityManagerInterface $entityManager = null, bool $granted = true): UiMcpProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);
        $mediaUrl = $this->createStub(MediaUrlExtension::class);
        $mediaUrl->method('getUrl')->willReturnCallback(static fn (Media $media): string => '/medias/' . $media->getFilename());
        $usageProvider = $this->createStub(MediaUsageProviderInterface::class);
        $usageProvider->method('getUsages')->willReturnCallback(static fn (array $medias): array => array_fill_keys(array_map(static fn (Media $media): int => (int) $media->getId(), $medias), [['label' => 'catalog', 'url' => null]]));
        $registry = new MediaUsageRegistry();
        $registry->addProvider($usageProvider);

        return new UiMcpProvider(
            $repository ?? $this->createStub(MediaRepository::class),
            $registry,
            $mediaUrl,
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            $configService,
            $security,
        );
    }
}
