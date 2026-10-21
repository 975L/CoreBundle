<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class EasyAdminActionHelperTest extends TestCase
{
    public function testToIconOnlyHidesLabelAndSetsTitle(): void
    {
        $action = Action::new('edit', 'Edit')->linkToCrudAction('edit');

        $result = EasyAdminActionHelper::toIconOnly($action, 'Edit');

        $this->assertSame($action, $result);
        $this->assertFalse($result->getAsDto()->getLabel());
        $this->assertSame(['title' => 'Edit'], $result->getAsDto()->getHtmlAttributes());
    }

    public function testToIconOnlyKeepsExistingHtmlAttributes(): void
    {
        $action = Action::new('preview', 'Preview')
            ->linkToUrl('https://example.com')
            ->setHtmlAttributes(['target' => '_blank']);

        $result = EasyAdminActionHelper::toIconOnly($action, 'Preview');

        $this->assertSame(['target' => '_blank', 'title' => 'Preview'], $result->getAsDto()->getHtmlAttributes());
    }

    public function testCancelLinksBackToTheIndex(): void
    {
        $dto = EasyAdminActionHelper::cancel('Cancel')->getAsDto();

        $this->assertSame('cancel', $dto->getName());
        $this->assertSame('Cancel', $dto->getLabel());
        $this->assertSame('fa fa-times', $dto->getIcon());
        $this->assertSame(Action::INDEX, $dto->getCrudActionName());
    }

    public function testIconOnlyDefaultsTurnsEditAndDeleteIntoIcons(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $actions = Actions::new()
            ->add(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DELETE);

        $index = EasyAdminActionHelper::iconOnlyDefaults($actions, $translator)->getAsDto(Crud::PAGE_INDEX);

        foreach ([Action::EDIT, Action::DELETE] as $name) {
            $action = $index->getAction(Crud::PAGE_INDEX, $name);
            $this->assertFalse($action->getLabel(), $name);
            $this->assertSame('action.' . $name, $action->getHtmlAttributes()['title'], $name);
        }
    }

    public function testIconOnlyDefaultsLeavesTheOthersAlone(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $actions = Actions::new()
            ->add(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DELETE);

        $index = EasyAdminActionHelper::iconOnlyDefaults($actions, $translator, [Action::DELETE])->getAsDto(Crud::PAGE_INDEX);

        $this->assertNotFalse($index->getAction(Crud::PAGE_INDEX, Action::EDIT)->getLabel());
        $this->assertFalse($index->getAction(Crud::PAGE_INDEX, Action::DELETE)->getLabel());
    }
}
