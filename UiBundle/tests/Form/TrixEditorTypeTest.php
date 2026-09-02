<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Form\TrixEditorType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;

class TrixEditorTypeTest extends TestCase
{
    public function testBuildViewMarksTheWidgetAsATrixEditor(): void
    {
        $type = new TrixEditorType();
        $view = new FormView();
        $view->vars['attr'] = [];
        $type->buildView($view, $this->createStub(FormInterface::class), []);

        $this->assertSame('1', $view->vars['attr']['data-trix']);
    }

    // Both directions, the editor having to be handed content it can display as much as the database has to be spared what it cannot - see StripInlineStyleTransformer
    public function testInlineStylesAreStrippedOnTheWayInAndOut(): void
    {
        $factory = Forms::createFormFactory();
        $form = $factory->create(TrixEditorType::class, '<div style="color: red">stored</div>');

        $this->assertSame('<div>stored</div>', $form->createView()->vars['value']);

        $form->submit('<div style="text-align: center">typed</div>');

        $this->assertSame('<div>typed</div>', $form->getData());
    }

    public function testGetParentIsTextareaType(): void
    {
        $type = new TrixEditorType();

        $this->assertSame(TextareaType::class, $type->getParent());
    }

    public function testGetBlockPrefix(): void
    {
        $type = new TrixEditorType();

        $this->assertSame('trix_editor', $type->getBlockPrefix());
    }
}
