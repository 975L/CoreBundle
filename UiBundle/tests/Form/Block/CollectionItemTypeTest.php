<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\CollectionItemType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CollectionItemTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new CollectionItemType()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['title', 'content', 'url', 'imageUrl', 'buttonLabel', 'buttonIcon', 'detailUrl'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the CollectionItem form");
        }
    }

    // Every field is optional - CollectionExtension::renderItems() fills only whichever of them a given CollectionItem/source actually provides
    public function testEveryFieldIsOptional(): void
    {
        $added = $this->buildAddedFields();

        foreach ($added as $field => $options) {
            $this->assertFalse($options['required'], "\"$field\" should be optional on the CollectionItem form");
        }
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new CollectionItemType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
