<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Contract\FormPageUrlProviderInterface;
use c975L\UiBundle\Registry\FormPageUrlRegistry;
use c975L\UiBundle\Twig\FormUrlExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AttributeExtension;

class FormUrlExtensionTest extends TestCase
{
    public function testItDeclaresTheFormUrlFunction(): void
    {
        $extension = new FormUrlExtension(new FormPageUrlRegistry(), $this->createStub(UrlGeneratorInterface::class));

        $names = array_map(static fn ($function): string => $function->getName(), new AttributeExtension(FormUrlExtension::class)->getFunctions());

        $this->assertSame(['form_url'], $names);
    }

    // A bundle contributing a richer page for that Form wins - SiteBundle's Page carrying the matching "form" Block, an admin-editable per-locale slug
    public function testGetFormUrlReturnsTheProviderPageWhenThereIsOne(): void
    {
        $provider = $this->createStub(FormPageUrlProviderInterface::class);
        $provider->method('getFormPageUrl')->willReturn('/en/contact-us');

        $registry = new FormPageUrlRegistry();
        $registry->addProvider($provider);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->never())->method('generate');

        $extension = new FormUrlExtension($registry, $urlGenerator);

        $this->assertSame('/en/contact-us', $extension->getFormUrl('contact'));
    }

    // The bare route always exists, so a template linking to a form never has to know which bundles are installed
    public function testGetFormUrlFallsBackOnTheBareFormRoute(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('ui_form_submit', ['name' => 'contact'])
            ->willReturn('/form/contact');

        $extension = new FormUrlExtension(new FormPageUrlRegistry(), $urlGenerator);

        $this->assertSame('/form/contact', $extension->getFormUrl('contact'));
    }
}
