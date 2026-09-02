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
}
