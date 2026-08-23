<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\EmailTemplateCrudController;
use c975L\UiBundle\Controller\Management\FormFieldTemplateCrudController;
use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Form\EmailBlockType;
use c975L\UiBundle\Registry\EmailAttachmentRegistry;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailTemplateCrudControllerTest extends TestCase
{
    /**
     * The order's lines cannot be taken out of an order confirmation.
     *
     * Deleting a collection row is a DOM removal, so what reaches the server is simply a submission the block is
     * missing from - there is no "delete" to refuse. It is put back here, and put back whole: an entry rebuilt with
     * only its id would null every field it left out, PRE_SUBMIT clearing what is absent.
     */
    public function testARestrictedTemplateKeepsItsDataBlocksWhenASubmissionDropsThem(): void
    {
        $emailTemplate = new EmailTemplate()->setName('confirm_order')->setLocale('fr')->setRestricted(true);
        $emailTemplate->addBlock($this->block(1, EmailBlock::TYPE_TEXT, 'Merci', null, 0));
        $emailTemplate->addBlock($this->block(2, EmailBlock::TYPE_SLOT, null, 'items', 1));

        // Only the text block comes back: the slot's row was removed in the browser
        $restored = $this->restoreDataBlocks($emailTemplate, [
            0 => ['id' => '1', 'type' => EmailBlock::TYPE_TEXT, 'content' => 'Merci', 'position' => '0'],
        ]);

        $this->assertCount(2, $restored);
        $this->assertArrayHasKey(1, $restored, 'The slot must come back under the key its form child already carries, or the prototype builds a second block instead');
        $this->assertSame('2', $restored[1]['id']);
        $this->assertSame(EmailBlock::TYPE_SLOT, $restored[1]['type']);
        $this->assertSame('items', $restored[1]['label'], 'A slot with no label names no fragment and renders nothing - the deletion would simply have happened one step later');
        $this->assertSame('1', $restored[1]['position']);
    }

    // An admin's own template is theirs entirely - the protection covers what a bundle declared and a site was seeded with
    public function testAnUnrestrictedTemplateLetsItsDataBlocksGo(): void
    {
        $emailTemplate = new EmailTemplate()->setName('my_own')->setLocale('fr')->setRestricted(false);
        $emailTemplate->addBlock($this->block(2, EmailBlock::TYPE_SLOT, null, 'items', 0));

        $this->assertSame([], $this->restoreDataBlocks($emailTemplate, []));
    }

    // Wording is the admin's: a text block dropped stays dropped, on a restricted template too
    public function testARestrictedTemplateStillLetsItsSentencesGo(): void
    {
        $emailTemplate = new EmailTemplate()->setName('confirm_order')->setLocale('fr')->setRestricted(true);
        $emailTemplate->addBlock($this->block(1, EmailBlock::TYPE_TEXT, 'Merci', null, 0));

        $this->assertSame([], $this->restoreDataBlocks($emailTemplate, []));
    }

    /** @return array<mixed> */
    private function restoreDataBlocks(EmailTemplate $emailTemplate, array $submitted): array
    {
        $method = new \ReflectionMethod(EmailTemplateCrudController::class, 'restoreDataBlocks');

        return $method->invoke($this->createController(), $emailTemplate, $this->blocksForm($emailTemplate), $submitted);
    }

    // A real form, not a double: the keys the entries are filed under are the form's own, and that is half of what is being checked
    private function blocksForm(EmailTemplate $emailTemplate): FormInterface
    {
        $form = Forms::createFormFactory()->createBuilder(FormType::class, null, ['data_class' => null])
            ->add('blocks', CollectionType::class, ['entry_type' => EmailBlockType::class, 'allow_add' => true, 'allow_delete' => true])
            ->getForm();

        $form->get('blocks')->setData($emailTemplate->getBlocks());

        return $form;
    }

    private function block(int $id, string $type, ?string $content, ?string $label, int $position): EmailBlock
    {
        $block = new EmailBlock()->setType($type)->setContent($content)->setLabel($label)->setPosition($position);

        $property = new \ReflectionProperty(EmailBlock::class, 'id');
        $property->setValue($block, $id);

        return $block;
    }

    private function createController(?AdminUrlGeneratorInterface $adminUrlGenerator = null): EmailTemplateCrudController
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        return new EmailTemplateCrudController(
            $configService,
            new AdminContextProvider(new RequestStack()),
            $this->createStub(EmailTemplateRenderer::class),
            new EmailAttachmentRegistry(),
            $adminUrlGenerator ?? $this->createStub(AdminUrlGeneratorInterface::class),
            $this->createStub(TranslatorInterface::class),
        );
    }

    public function testGetEntityFqcnReturnsEmailTemplate(): void
    {
        $this->assertSame(EmailTemplate::class, EmailTemplateCrudController::getEntityFqcn());
    }

    public function testConfigureActionsGrantsEveryActionToTheAdminRole(): void
    {
        $controller = $this->createController();

        // A real EasyAdmin runtime pre-populates default actions before calling configureActions()
        $actions = $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $permissions = $actions->getAsDto(null)->getActionPermissions();
        $this->assertSame('ROLE_ADMIN', $permissions[Action::INDEX]);
        $this->assertSame('ROLE_ADMIN', $permissions[Action::NEW]);
        $this->assertSame('ROLE_ADMIN', $permissions[Action::EDIT]);
        $this->assertSame('ROLE_ADMIN', $permissions[Action::DELETE]);
        $this->assertSame('ROLE_ADMIN', $permissions['formFieldTemplates']);
    }

    // Detail adds no information beyond what edit already shows - disabled entirely, and a Cancel action lets the admin back out of a create/edit without saving
    public function testConfigureActionsDisablesDetailAndAddsCancelOnNewAndEdit(): void
    {
        $controller = $this->createController();

        $actions = $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $this->assertContains(Action::DETAIL, $actions->getAsDto(null)->getDisabledActions());
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_NEW)->getAction(Crud::PAGE_NEW, 'cancel'));
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_EDIT)->getAction(Crud::PAGE_EDIT, 'cancel'));
    }

    public function testConfigureActionsHidesDeleteForARestrictedTemplate(): void
    {
        $controller = $this->createController();

        $actions = $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        )->getAsDto(null);

        $deleteAction = $actions->getActions()[Crud::PAGE_INDEX][Action::DELETE];

        $this->assertNotNull($deleteAction);
        // No public getter for the display callable - read the private property directly
        $reflection = new \ReflectionProperty($deleteAction, 'displayCallable');
        $displayCallable = $reflection->getValue($deleteAction);

        $this->assertFalse($displayCallable(new EmailTemplate()->setRestricted(true)));
        $this->assertTrue($displayCallable(new EmailTemplate()->setRestricted(false)));
    }

    // The index page's own global button must point at FormFieldTemplateCrudController, not a sidebar menu entry (see ChangeLog)
    public function testConfigureActionsAddsAGlobalButtonLinkingToFormFieldTemplates(): void
    {
        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('unsetAll')->willReturnSelf();
        $urlGenerator->expects($this->once())->method('setController')->with(FormFieldTemplateCrudController::class)->willReturnSelf();
        $urlGenerator->method('generateUrl')->willReturn('/management/form-field-template');

        $controller = $this->createController($urlGenerator);

        $actions = $controller->configureActions(
            Actions::new()->add(Crud::PAGE_INDEX, Action::EDIT)->add(Crud::PAGE_INDEX, Action::DELETE)
        )->getAsDto(null);

        $action = $actions->getActions()[Crud::PAGE_INDEX]['formFieldTemplates'];

        $this->assertNotNull($action);
        $this->assertSame('/management/form-field-template', $action->getUrl());
    }
}
