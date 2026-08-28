<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Controller\CalculatorController;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Repository\FormRepository;
use c975L\UiBundle\Service\CalculatorExpressionLanguage;
use c975L\UiBundle\Service\ExpressionEvaluator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalculatorControllerTest extends TestCase
{
    private function createCalculator(): Form
    {
        $form = new Form();
        $form->setName('economies');

        $field = new FormField();
        $field->setLabel('Km par an')->setName('km-par-an')->setType(FormField::TYPE_RANGE)->setDefaultValue('15000');
        $form->addField($field);

        $output = new FormOutput();
        $output->setLabel('Litres')->setName('litres')->setExpression('km_par_an / 100 * 7');
        $form->addOutput($output);

        return $form;
    }

    private function createController(?Form $form): CalculatorController
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn($form);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $translator->method('getLocale')->willReturn('fr');

        return new CalculatorController($repository, new ExpressionEvaluator(new CalculatorExpressionLanguage(), $translator));
    }

    public function testComputeAnswersEveryOutputAsJson(): void
    {
        $response = $this->createController($this->createCalculator())->compute('economies', new Request(['km-par-an' => '20000']));

        $this->assertEqualsWithDelta(1400.0, json_decode((string) $response->getContent(), true)['litres']['value'], 0.001);
    }

    public function testComputeFallsBackToTheDefaultsWhenNothingIsPassed(): void
    {
        $response = $this->createController($this->createCalculator())->compute('economies', new Request());

        $this->assertEqualsWithDelta(1050.0, json_decode((string) $response->getContent(), true)['litres']['value'], 0.001);
    }

    // Someone's fuel consumption is their own: a shared cache holding one answer would hand it to the next visitor
    public function testComputeIsNeverStoredByAnyCache(): void
    {
        $response = $this->createController($this->createCalculator())->compute('economies', new Request());

        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    public function testComputeThrowsNotFoundWhenTheFormDoesNotExist(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(null)->compute('inconnu', new Request());
    }

    public function testComputeThrowsNotFoundWhenTheFormIsNotACalculator(): void
    {
        $form = new Form();
        $form->setName('contact')->setAction('send_email');

        $this->expectException(NotFoundHttpException::class);

        $this->createController($form)->compute('contact', new Request());
    }

    public function testComputeThrowsNotFoundWhenTheCalculatorIsDisabled(): void
    {
        $form = $this->createCalculator();
        $form->setEnabled(false);

        $this->expectException(NotFoundHttpException::class);

        $this->createController($form)->compute('economies', new Request());
    }
}
