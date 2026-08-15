<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\TextHookType;
use c975L\UiBundle\Form\TrixEditorType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TextHookTypeTest extends TestCase
{
    /**
     * @return array<string, array{type: ?string, options: array}>
     */
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        new TextHookType()->buildForm($builder, []);

        return $added;
    }

    // A hook is one paragraph and nothing else of its own, which is what keeps it droppable anywhere - the site classes beside it style that paragraph rather than adding anything to it (see HasCssClassesFieldTrait)
    public function testBuildFormAddsTheTextFieldAndNothingButTheSiteClasses(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(['text', 'cssClasses'], array_keys($added));
        $this->assertSame('label.text', $added['text']['options']['label']);
    }

    // Rich text: the point of the kind is the class the editor cannot write, not plain text
    public function testTheTextFieldIsARichTextEditor(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(TrixEditorType::class, $added['text']['type']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new TextHookType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
