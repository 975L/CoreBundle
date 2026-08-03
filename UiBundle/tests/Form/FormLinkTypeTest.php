<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Form\FormLinkType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormLinkTypeTest extends TestCase
{
    private function buildFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        (new FormLinkType())->buildForm($builder, []);

        return $added;
    }

    public function testARowIsAlabelAndAnUrl(): void
    {
        $added = $this->buildFields();

        $this->assertSame(['label', 'url'], array_keys($added));
        $this->assertSame(TextType::class, $added['label']['type']);
        $this->assertSame(TextType::class, $added['url']['type']);
    }

    // An entry is an array stored in Form::$actionConfig, not an entity - a data_class here would make Symfony try to instantiate one
    public function testEntriesAreMappedAsPlainArrays(): void
    {
        $resolver = new OptionsResolver();
        (new FormLinkType())->configureOptions($resolver);

        $options = $resolver->resolve();
        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
        $this->assertFalse($options['label']);
    }
}
