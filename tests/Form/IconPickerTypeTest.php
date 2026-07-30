<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Form\IconPickerType;
use c975L\UiBundle\Service\IconServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IconPickerTypeTest extends TestCase
{
    private function createIconService(array $icons): IconServiceInterface
    {
        $service = $this->createStub(IconServiceInterface::class);
        $service->method('getIcons')->willReturn($icons);

        return $service;
    }

    public function testBuildViewExposesTheFullIconSetByDefault(): void
    {
        $type = new IconPickerType($this->createIconService(['home' => 'icons/home.svg', 'star' => 'icons/star.svg']));
        $view = new FormView();
        $type->buildView($view, $this->createStub(FormInterface::class), ['icons' => null, 'value_field' => 'path']);

        $this->assertSame(['home' => 'icons/home.svg', 'star' => 'icons/star.svg'], $view->vars['icons']);
        $this->assertSame('path', $view->vars['value_field']);
    }

    // A picker restricted to a subset of keys (e.g. social networks only) must not expose the rest
    public function testBuildViewRestrictsIconsWhenIconsOptionIsSet(): void
    {
        $type = new IconPickerType($this->createIconService([
            'home' => 'icons/home.svg',
            'star' => 'icons/star.svg',
            'facebook' => 'icons/facebook.svg',
        ]));
        $view = new FormView();
        $type->buildView($view, $this->createStub(FormInterface::class), ['icons' => ['facebook'], 'value_field' => 'name']);

        $this->assertSame(['facebook' => 'icons/facebook.svg'], $view->vars['icons']);
        $this->assertSame('name', $view->vars['value_field']);
    }

    public function testGetParentIsTextType(): void
    {
        $type = new IconPickerType($this->createIconService([]));

        $this->assertSame(TextType::class, $type->getParent());
    }

    public function testGetBlockPrefix(): void
    {
        $type = new IconPickerType($this->createIconService([]));

        $this->assertSame('icon_picker', $type->getBlockPrefix());
    }

    public function testConfigureOptionsDefaults(): void
    {
        $type = new IconPickerType($this->createIconService([]));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['icons']);
        $this->assertSame('path', $options['value_field']);
    }

    public function testConfigureOptionsRejectsInvalidValueField(): void
    {
        $type = new IconPickerType($this->createIconService([]));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $this->expectException(InvalidOptionsException::class);
        $resolver->resolve(['value_field' => 'invalid']);
    }
}
