<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Controller\Management\FormCrudController;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Repository\FormRepository;
use c975L\UiBundle\Service\FormEditUrl;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

// The pencil over a form block opens the Form's own screen, where its fields, their labels and where it posts actually live - the page's own form only holds the name
class FormEditUrlTest extends TestCase
{
    public function testAFormBlockLeadsToTheFormsOwnScreen(): void
    {
        $url = FormEditUrl::build(
            $this->adminUrlGenerator(),
            $this->formRepository($this->form(7)),
            $this->block('form', ['name' => 'contact'])
        );

        $this->assertSame('/admin?crud=' . FormCrudController::class . '&action=edit&id=7', $url);
    }

    // Anything else is edited where it is, so the button keeps its ordinary target
    public function testAnyOtherKindOfBlockIsLeftAlone(): void
    {
        $this->assertNull(FormEditUrl::build(
            $this->adminUrlGenerator(),
            $this->formRepository($this->form(7)),
            $this->block('text', ['name' => 'contact'])
        ));
    }

    // A name no Form answers to would 404, so the page's own form stays the right place to fix it
    public function testANameNoFormAnswersToLeadsNowhere(): void
    {
        $this->assertNull(FormEditUrl::build(
            $this->adminUrlGenerator(),
            $this->formRepository(null),
            $this->block('form', ['name' => 'gone'])
        ));
    }

    public function testABlockCarryingNoNameLeadsNowhere(): void
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn($this->form(7));

        $this->assertNull(FormEditUrl::build($this->adminUrlGenerator(), $repository, $this->block('form', [])));
        $this->assertNull(FormEditUrl::build($this->adminUrlGenerator(), $repository, $this->block('form', ['name' => ''])));
    }

    private function block(string $kind, array $data): Block
    {
        return new Block()->setKind($kind)->setData($data);
    }

    private function form(int $id): Form
    {
        $form = new Form();
        $reflection = new \ReflectionProperty(Form::class, 'id');
        $reflection->setValue($form, $id);

        return $form;
    }

    private function formRepository(?Form $form): FormRepository
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn($form);

        return $repository;
    }

    private function adminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $controller = null;
        $action = null;
        $id = null;

        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnCallback(function (string $value) use ($generator, &$controller) {
            $controller = $value;

            return $generator;
        });
        $generator->method('setAction')->willReturnCallback(function (string $value) use ($generator, &$action) {
            $action = $value;

            return $generator;
        });
        $generator->method('setEntityId')->willReturnCallback(function ($value) use ($generator, &$id) {
            $id = $value;

            return $generator;
        });
        $generator->method('generateUrl')->willReturnCallback(
            function () use (&$controller, &$action, &$id): string {
                return sprintf('/admin?crud=%s&action=%s&id=%s', $controller, $action, $id);
            }
        );

        return $generator;
    }
}
