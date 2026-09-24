<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity;

use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// A row added to a form's fields and left empty used to be saved as is, where the column refuses a label of nothing
class FormFieldValidationTest extends TestCase
{
    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    // The back office submits its forms without the browser's own check, so the refusal has to come from the entity
    public function testAFieldWithoutALabelIsRefused(): void
    {
        // The property alone: the entity also carries a UniqueEntity, whose validator wants a Doctrine registry this test has no business booting
        $violations = $this->validator()->validateProperty(new FormField(), 'label');

        $this->assertCount(1, $violations);
        $this->assertSame('label', $violations->get(0)->getPropertyPath());
    }

    // Refusing the row is only worth something if the form it was added to looks inside its own collection
    public function testTheFormValidatesEachOfItsFields(): void
    {
        $fields = $this->validator()->getMetadataFor(Form::class)->getPropertyMetadata('fields');

        $this->assertNotEmpty($fields);
        $this->assertSame(CascadingStrategy::CASCADE, $fields[0]->getCascadingStrategy());
    }

    // Two options sharing a value are numbered 0, 1, 2... by Symfony, which a formula then reads instead of the values typed
    public function testTwoOptionsCannotShareAValue(): void
    {
        // The callback alone, for the same reason as above: the entity's UniqueEntity wants a Doctrine registry
        $callback = new Assert\Callback(static fn (FormField $field, ExecutionContextInterface $context) => $field->validateOptionValues($context));
        $field = new FormField()->setOptions([['label' => '2 mois', 'value' => '1'], ['label' => 'Autre', 'value' => '1']]);

        $violations = $this->validator()->validate($field, $callback);
        $this->assertCount(1, $violations);
        $this->assertSame('optionsText', $violations->get(0)->getPropertyPath());
        $this->assertSame(['%values%' => '1'], $violations->get(0)->getParameters());

        $field->setOptions([['label' => '2 mois', 'value' => '1'], ['label' => 'Autre', 'value' => '1.0']]);
        $this->assertCount(0, $this->validator()->validate($field, $callback));
    }
}
