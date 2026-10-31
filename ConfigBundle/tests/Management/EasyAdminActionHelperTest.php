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
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Twig\Component\Option\ButtonVariant;
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
        $this->assertSame(['title' => 'Edit', 'aria-label' => 'Edit'], $result->getAsDto()->getHtmlAttributes());
    }

    public function testToIconOnlyKeepsExistingHtmlAttributes(): void
    {
        $action = Action::new('preview', 'Preview')
            ->linkToUrl('https://example.com')
            ->setHtmlAttributes(['target' => '_blank']);

        $result = EasyAdminActionHelper::toIconOnly($action, 'Preview');

        $this->assertSame(['target' => '_blank', 'title' => 'Preview', 'aria-label' => 'Preview'], $result->getAsDto()->getHtmlAttributes());
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

    public function testIconOnlyDefaultsSkipsAnActionMissingFromTheIndex(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $actions = Actions::new()->add(Crud::PAGE_INDEX, Action::EDIT);

        $index = EasyAdminActionHelper::iconOnlyDefaults($actions, $translator)->getAsDto(Crud::PAGE_INDEX);

        $this->assertFalse($index->getAction(Crud::PAGE_INDEX, Action::EDIT)->getLabel());
        $this->assertNull($index->getAction(Crud::PAGE_INDEX, Action::DELETE));
    }

    // The app goes on configuring after it: a reorder() reads every page of the dto
    public function testIconOnlyDefaultsLeavesTheActionsReorderable(): void
    {
        $actions = Actions::new()->add(Crud::PAGE_INDEX, Action::EDIT)->add(Crud::PAGE_INDEX, Action::DELETE);

        EasyAdminActionHelper::iconOnlyDefaults($actions, $this->createStub(TranslatorInterface::class))->reorder(Crud::PAGE_INDEX, [Action::DELETE, Action::EDIT]);

        $this->assertSame([Action::DELETE, Action::EDIT], array_keys($actions->getAsDto(null)->getActions()[Crud::PAGE_INDEX]));
    }

    // From the list, the batch action says it moves the selection to the recycle bin, in warning, leaving the confirmation to EasyAdmin's own batch modal
    public function testTrashBatchDeleteSaysMoveToTrashOnTheList(): void
    {
        $actions = Actions::new()->add(Crud::PAGE_INDEX, Action::BATCH_DELETE);

        $action = EasyAdminActionHelper::trashBatchDelete($actions, false, $this->createStub(AdminUrlGeneratorInterface::class))->getAsDto(null)->getAction(Crud::PAGE_INDEX, Action::BATCH_DELETE);

        $this->assertSame('action.move_to_trash', $action->getLabel()->getMessage());
        $this->assertSame('fa fa-box-archive', $action->getIcon());
        $this->assertSame(ButtonVariant::Warning, $action->getVariant());
        $this->assertFalse($action->getConfirmationMessage() ?? false);
    }

    // From the recycle bin, the same action says it removes the selection for good, keeping EasyAdmin's danger
    public function testTrashBatchDeleteSaysDeletePermanentlyInTheBin(): void
    {
        $actions = Actions::new()->add(Crud::PAGE_INDEX, Action::BATCH_DELETE);

        $action = EasyAdminActionHelper::trashBatchDelete($actions, true, $this->createStub(AdminUrlGeneratorInterface::class))->getAsDto(null)->getAction(Crud::PAGE_INDEX, Action::BATCH_DELETE);

        $this->assertSame('action.delete_permanently', $action->getLabel()->getMessage());
        $this->assertSame('fa fa-trash', $action->getIcon());
        $this->assertSame(ButtonVariant::Danger, $action->getVariant());
        $this->assertFalse($action->getConfirmationMessage() ?? false);
    }

    // From the bin, the batch is posted with "trash=1", for deleteEntity() to know the screen the selection came from
    public function testTrashBatchDeletePostsTheBinsFlag(): void
    {
        $adminUrlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->expects($this->once())->method('setAction')->with(Action::BATCH_DELETE)->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('set')->with('trash', 1)->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/admin/batch-delete?trash=1');
        $actions = Actions::new()->add(Crud::PAGE_INDEX, Action::BATCH_DELETE);

        $url = EasyAdminActionHelper::trashBatchDelete($actions, true, $adminUrlGenerator)->getAsDto(null)->getAction(Crud::PAGE_INDEX, Action::BATCH_DELETE)->getUrl();

        $this->assertSame('/admin/batch-delete?trash=1', $url());
    }

    // From the list, EasyAdmin's own url is kept: no flag, the selection is moved to the bin
    public function testTrashBatchDeleteKeepsEasyAdminsUrlOnTheList(): void
    {
        $actions = Actions::new()->add(Crud::PAGE_INDEX, Action::BATCH_DELETE);

        $action = EasyAdminActionHelper::trashBatchDelete($actions, false, $this->createStub(AdminUrlGeneratorInterface::class))->getAsDto(null)->getAction(Crud::PAGE_INDEX, Action::BATCH_DELETE);

        $this->assertNull($action->getUrl());
    }

    // A screen that took the batch action off is left alone rather than made to throw
    public function testTrashBatchDeleteSkipsAScreenWithoutTheAction(): void
    {
        $actions = Actions::new()->add(Crud::PAGE_INDEX, Action::EDIT);

        $this->assertSame($actions, EasyAdminActionHelper::trashBatchDelete($actions, true, $this->createStub(AdminUrlGeneratorInterface::class)));
        $this->assertNull($actions->getAsDto(null)->getAction(Crud::PAGE_INDEX, Action::BATCH_DELETE));
    }
}
