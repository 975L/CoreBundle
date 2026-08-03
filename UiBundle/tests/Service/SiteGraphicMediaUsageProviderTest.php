<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\SiteGraphicMediaUsageProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteGraphicMediaUsageProviderTest extends TestCase
{
    private function createProvider(): SiteGraphicMediaUsageProvider
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/admin/edit');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        return new SiteGraphicMediaUsageProvider($generator, $translator);
    }

    private function mediaWithId(int $id, ?string $role = null): Media
    {
        $media = (new Media())->setRole($role);
        (new \ReflectionProperty(Media::class, 'id'))->setValue($media, $id);

        return $media;
    }

    // A media carrying a role is reported as used, with a link to its own edit screen
    public function testGetUsagesReportsSiteGraphicRole(): void
    {
        $usages = $this->createProvider()->getUsages([$this->mediaWithId(1, Media::ROLE_FAVICON)]);

        $this->assertSame('label.favicon', $usages[1][0]['label']);
        $this->assertSame('/admin/edit', $usages[1][0]['url']);
    }

    // A role with no label of its own (a row left behind by an older version) still reports, under its raw role
    public function testGetUsagesFallsBackOnTheRoleItselfWhenUnlabelled(): void
    {
        $usages = $this->createProvider()->getUsages([$this->mediaWithId(2, 'unknown-role')]);

        $this->assertSame('unknown-role', $usages[2][0]['label']);
    }

    // A media without any role belongs to a Block or to another bundle's entity, not here
    public function testGetUsagesIgnoresMediaWithoutRole(): void
    {
        $this->assertSame([], $this->createProvider()->getUsages([$this->mediaWithId(3)]));
    }
}
