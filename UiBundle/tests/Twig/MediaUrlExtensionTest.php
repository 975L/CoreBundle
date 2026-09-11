<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Controller\MediaController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Twig\MediaUrlExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AttributeExtension;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

class MediaUrlExtensionTest extends TestCase
{
    private function createExtension(): MediaUrlExtension
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters = []): string => $name . '/' . $parameters['id']
        );

        $uploaderHelper = $this->createStub(UploaderHelperInterface::class);
        $uploaderHelper->method('asset')->willReturn('/medias/site/tree.pdf');

        return new MediaUrlExtension($urlGenerator, $uploaderHelper);
    }

    private function createMedia(bool $membersOnly): Media
    {
        $media = new Media()->setFilename('medias/site/tree.pdf')->setMembersOnly($membersOnly);
        new \ReflectionProperty(Media::class, 'id')->setValue($media, 34);

        return $media;
    }

    public function testAPublicMediaKeepsTheWebServersAddress(): void
    {
        $this->assertSame('/medias/site/tree.pdf', $this->createExtension()->getUrl($this->createMedia(false)));
    }

    // public/ no longer holds it: the address has to be the route a member opens it from
    public function testAMediaReservedToMembersGoesThroughItsRoute(): void
    {
        $this->assertSame(MediaController::ROUTE . '/34', $this->createExtension()->getUrl($this->createMedia(true)));
    }

    public function testTheFunctionIsRegistered(): void
    {
        $names = array_map(static fn ($function) => $function->getName(), new AttributeExtension(MediaUrlExtension::class)->getFunctions());

        $this->assertSame(['media_url'], $names);
    }
}
