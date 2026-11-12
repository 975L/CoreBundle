<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\ProgressBarType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validation;

class ProgressBarTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new ProgressBarType()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsLabelProgressPercentAndTextFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['label', 'progressPercent', 'text'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the ProgressBar form");
        }
    }

    // "label" is optional so pre-existing progress_bar blocks (created before this field existed) keep working unchanged
    public function testLabelFieldIsNotRequired(): void
    {
        $added = $this->buildAddedFields();

        $this->assertFalse($added['label']['required']);
    }

    private function createFactory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
    }

    // A bar whose percentage was switched off stays off when its page is saved again
    public function testASavedBarKeepsItsPercentageSwitchedOff(): void
    {
        $form = $this->createFactory()->create(ProgressBarType::class, ['label' => 'Goal', 'progressPercent' => 40, 'text' => false]);

        $this->assertFalse($form->get('text')->getData());
    }

    // A brand new bar shows its percentage by default
    public function testANewBarShowsItsPercentage(): void
    {
        $form = $this->createFactory()->create(ProgressBarType::class);

        $this->assertTrue($form->get('text')->getData());
    }

    // The 0-100 bounds shown in the input are enforced on submit too
    public function testAPercentageOutOfBoundsIsRefused(): void
    {
        $form = $this->createFactory()->create(ProgressBarType::class);
        $form->submit(['label' => 'Goal', 'progressPercent' => '150', 'text' => '1']);

        $this->assertFalse($form->isValid());
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new ProgressBarType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
