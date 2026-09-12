<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\MediaCrudController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Twig\EntityEditUrlExtension;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class EntityEditUrlExtensionTest extends TestCase
{
    // The CRUD is named after the entity, whichever namespace holds the pair
    public function testAnEditorIsGivenTheFormOfTheCrudNamedAfterTheEntity(): void
    {
        $generator = $this->createMock(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->expects($this->once())->method('setController')->with(MediaCrudController::class)->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/management/media/42/edit');

        $this->assertSame('/management/media/42/edit', $this->extension($generator)->editUrl($this->media(42)));
    }

    // What a leak would look like: the url of the back office handed to whoever is not an editor
    public function testAnyoneButAnEditorIsGivenNothing(): void
    {
        $generator = $this->generator('/management/media/42/edit');

        $this->assertNull($this->extension($generator, false)->editUrl($this->media(42)));
        $this->assertNull($this->extension($generator, true, 'ROLE_EDITOR', false)->editUrl($this->media(42)));
        $this->assertNull($this->extension($generator, true, '')->editUrl($this->media(42)), 'An empty editor role would grant the pencil to nobody in particular.');
    }

    // An object no CRUD edits, or one not saved yet, draws no pencil rather than raising on a public page
    public function testAnEntityWithoutACrudOrAnIdIsGivenNothing(): void
    {
        $extension = $this->extension($this->generator('/management/media/42/edit'));

        $this->assertNull($extension->editUrl(new class {
            public function getId(): int
            {
                return 1;
            }
        }));
        $this->assertNull($extension->editUrl(new Media()));
    }

    // EasyAdmin's route cache emptied under fresh compiled routes throws from every generateUrl(): the page stays up, the pencil goes
    public function testAnUrlThatCannotBeBuiltIsGivenAsNothing(): void
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        $generator->method('generateUrl')->willThrowException(new \RuntimeException('No dashboard'));

        $this->assertNull($this->extension($generator)->editUrl($this->media(42)));
        $this->assertNull($this->extension($generator)->editPattern());
    }

    // The pattern keeps the dashboard's own prefix and leaves the kind and the id to the controller
    public function testThePatternLeavesTheKindAndTheIdToFill(): void
    {
        $generator = $this->generator('/admin/media/__id__/edit');

        $this->assertSame('/admin/__kind__/__id__/edit', $this->extension($generator)->editPattern());
        $this->assertNull($this->extension($generator, false)->editPattern());
    }

    // Without pretty urls the kind is a query parameter holding a class name, which no mark could fill: better no pencil than one opening the media library
    public function testADashboardWithoutPrettyUrlsGivesNoPattern(): void
    {
        $this->assertNull($this->extension($this->generator('/admin?crudAction=edit&entityId=__id__'))->editPattern());
    }

    private function generator(string $url): AdminUrlGeneratorInterface
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        $generator->method('generateUrl')->willReturn($url);

        return $generator;
    }

    private function extension(AdminUrlGeneratorInterface $generator, bool $granted = true, string $role = 'ROLE_EDITOR', bool $withSecurity = true): EntityEditUrlExtension
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn($role);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);

        return new EntityEditUrlExtension($config, $generator, $withSecurity ? $security : null);
    }

    private function media(int $id): Media
    {
        $media = new Media();
        new \ReflectionProperty($media, 'id')->setValue($media, $id);

        return $media;
    }
}
