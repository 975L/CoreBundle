<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Form\MediaUsagesType;
use c975L\UiBundle\Registry\MediaUsageRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MediaUsagesTypeTest extends TestCase
{
    private function createForm(?object $parentData): FormInterface
    {
        $parent = $this->createStub(FormInterface::class);
        $parent->method('getData')->willReturn($parentData);

        $form = $this->createStub(FormInterface::class);
        $form->method('getParent')->willReturn($parent);

        return $form;
    }

    // Media::$id has no public setter (Doctrine-generated) - set directly via reflection to exercise the real "id => usages" lookup instead of the edge case of an unpersisted (id-less) Media
    private function createMediaWithId(int $id): Media
    {
        $media = new Media();
        $property = new \ReflectionProperty(Media::class, 'id');
        $property->setValue($media, $id);

        return $media;
    }

    public function testBuildViewSetsUsagesWhenParentDataIsMedia(): void
    {
        $media = $this->createMediaWithId(5);
        $usages = [['label' => 'Home page', 'url' => '/edit/1']];

        $registry = $this->createStub(MediaUsageRegistry::class);
        $registry->method('getUsages')->willReturn([5 => $usages]);

        $type = new MediaUsagesType($registry);
        $view = new FormView();
        $type->buildView($view, $this->createForm($media), []);

        $this->assertSame($usages, $view->vars['usages']);
        $this->assertSame(5, $view->vars['mediaId']);
    }

    public function testBuildViewSetsEmptyUsagesWhenParentDataIsNotMedia(): void
    {
        $registry = $this->createStub(MediaUsageRegistry::class);
        $registry->method('getUsages')->willReturn([]);

        $type = new MediaUsagesType($registry);
        $view = new FormView();
        $type->buildView($view, $this->createForm(new \stdClass()), []);

        $this->assertSame([], $view->vars['usages']);
        $this->assertNull($view->vars['mediaId']);
    }

    public function testBuildViewSetsEmptyUsagesWhenFormHasNoParent(): void
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('getParent')->willReturn(null);

        $registry = $this->createStub(MediaUsageRegistry::class);

        $type = new MediaUsagesType($registry);
        $view = new FormView();
        $type->buildView($view, $form, []);

        $this->assertSame([], $view->vars['usages']);
    }

    public function testGetParentIsTextType(): void
    {
        $type = new MediaUsagesType($this->createStub(MediaUsageRegistry::class));

        $this->assertSame(TextType::class, $type->getParent());
    }

    public function testGetBlockPrefix(): void
    {
        $type = new MediaUsagesType($this->createStub(MediaUsageRegistry::class));

        $this->assertSame('media_usages', $type->getBlockPrefix());
    }

    public function testConfigureOptionsDefaultsToUnmappedAndOptional(): void
    {
        $type = new MediaUsagesType($this->createStub(MediaUsageRegistry::class));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertFalse($options['mapped']);
        $this->assertFalse($options['required']);
    }
}
