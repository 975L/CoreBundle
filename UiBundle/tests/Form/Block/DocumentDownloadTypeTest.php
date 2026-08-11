<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\DocumentDownloadType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentDownloadTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new DocumentDownloadType()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsLabelAndButtonLabelFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['label', 'buttonLabel'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the DocumentDownload form");
        }
    }

    public function testButtonLabelFieldIsNotRequired(): void
    {
        $added = $this->buildAddedFields();

        $this->assertFalse($added['buttonLabel']['required']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new DocumentDownloadType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
