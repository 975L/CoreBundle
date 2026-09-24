<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Contract\LinkTargetProviderInterface;
use c975L\UiBundle\Form\LinkTargetType;
use c975L\UiBundle\Registry\LinkTargetRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

// Picked like a menu link, typed like an address: the list never refuses a link for not being in it
class LinkTargetTypeTest extends TypeTestCase
{
    protected function setUp(): void
    {
        // TypeTestCase would otherwise create a bare mock for this, which PHPUnit 13 flags as a notice
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);

        parent::setUp();
    }

    public function testATargetOfTheListIsKept(): void
    {
        $form = $this->factory->create(LinkTargetType::class);
        $form->submit('page:53#services-75');

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('page:53#services-75', $form->getData());
    }

    // "/shop", "https://…": an address the site lists nowhere is taken as typed
    public function testAnAddressTypedByHandIsKept(): void
    {
        $form = $this->factory->create(LinkTargetType::class);
        $form->submit('https://example.com/shop');

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('https://example.com/shop', $form->getData());
    }

    // Stored before the list existed, it shows under its own words rather than as an empty field about to wipe it at the next save
    public function testAnAddressAlreadyHeldIsOfferedAndSelected(): void
    {
        $view = $this->factory->create(LinkTargetType::class, '/shop')->createView();

        $this->assertSame('/shop', $view->vars['value']);
        $this->assertContains('/shop', array_map(static fn ($choice) => $choice->value, $view->vars['choices']));
        $this->assertSame('page:53', $view->vars['choices'][0]->value);
    }

    public function testNothingChosenStaysNothing(): void
    {
        $form = $this->factory->create(LinkTargetType::class, null, ['required' => false]);
        $form->submit('');

        $this->assertTrue($form->isSynchronized());
        $this->assertNull($form->getData());
    }

    // A new button starts with no link rather than with the first page of the list
    public function testARequiredFieldStartsEmpty(): void
    {
        $view = $this->factory->create(LinkTargetType::class, null, ['required' => true])->createView();

        $this->assertSame('', $view->vars['placeholder']);
        $this->assertSame('', $view->vars['value']);
    }

    #[\Override]
    protected function getExtensions(): array
    {
        $provider = $this->createStub(LinkTargetProviderInterface::class);
        $provider->method('linkTargets')->willReturn(['Publier → Services' => 'page:53#services-75', 'Publier' => 'page:53']);

        $registry = new LinkTargetRegistry();
        $registry->addProvider($provider);

        return [new PreloadedExtension([new LinkTargetType($registry)], [])];
    }
}
