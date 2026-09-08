<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\FaqItemType;
use c975L\UiBundle\Form\Block\FaqType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

class FaqTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        new FaqType(new BlockAnchorSlugger(new AsciiSlugger()))->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsTitleQuestionsOpenFirstColumnsAndAnchorFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['title', 'items', 'openFirst', 'columns', 'anchor'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the Faq form");
        }
    }

    // The questions are a collection an editor adds to and removes from, each entry drawn by the item type
    public function testItemsFieldIsACollectionOfFaqItemType(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(CollectionType::class, $added['items']['type']);
        $this->assertSame(FaqItemType::class, $added['items']['options']['entry_type']);
        $this->assertTrue($added['items']['options']['allow_add']);
        $this->assertTrue($added['items']['options']['allow_delete']);
    }

    // Only the title is optional beside them: a question with no answer is what the template filters out, not the form
    public function testTheFirstAnswerIsUnfoldedByACheckbox(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(CheckboxType::class, $added['openFirst']['type']);
        $this->assertFalse($added['openFirst']['options']['required']);
    }

    // Two columns and no placeholder, one being the default the template reads - the structured data is published on one column only
    public function testTheLayoutOffersOneOrTwoColumnsWithNoEmptyChoice(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(ChoiceType::class, $added['columns']['type']);
        $this->assertSame(['1' => 1, '2' => 2], $added['columns']['options']['choices']);
        $this->assertFalse($added['columns']['options']['placeholder']);
        $this->assertFalse($added['columns']['options']['choice_translation_domain']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new FaqType(new BlockAnchorSlugger(new AsciiSlugger()));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
