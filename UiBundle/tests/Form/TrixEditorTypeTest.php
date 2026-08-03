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
