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
use c975L\UiBundle\Controller\Management\FormFieldTemplateCrudController;
use c975L\UiBundle\Entity\FormFieldTemplate;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormFieldTemplateCrudControllerTest extends TestCase
{
    private function createController(): FormFieldTemplateCrudController
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_ADMIN');

        return new FormFieldTemplateCrudController(
            $configService,
            new AdminContextProvider(new RequestStack()),
            $this->createStub(TranslatorInterface::class),
        );
    }

    public function testGetEntityFqcnReturnsFormFieldTemplate(): void
    {
        $this->assertSame(FormFieldTemplate::class, FormFieldTemplateCrudController::getEntityFqcn());
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

        $this->assertFalse($displayCallable(new FormFieldTemplate()->setRestricted(true)));
        $this->assertTrue($displayCallable(new FormFieldTemplate()->setRestricted(false)));
    }

    // A seeded field template is left in place by the server with a warning, EasyAdmin's batch delete calling deleteEntity() without asking displayIf()
    public function testDeleteEntityLeavesARestrictedFormFieldTemplate(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('remove');

        $session = new Session(new MockArraySessionStorage());
        $controller = $this->createController();
        $controller->setContainer($this->flashContainer($session));

        $controller->deleteEntity($entityManager, new FormFieldTemplate()->setRestricted(true));

        $this->assertCount(1, $session->getFlashBag()->get('warning'), 'A seeded field template left in place said nothing, a batch delete ending on it silent.');
    }

    // What addFlash() reaches: the request stack holding a session
    private function flashContainer(Session $session): Container
    {
        $request = new Request();
        $request->setSession($session);
        $container = new Container();
        $container->set('request_stack', new RequestStack([$request]));

        return $container;
    }

    // An admin's own field template goes as before
    public function testDeleteEntityRemovesAnUnrestrictedFormFieldTemplate(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove');

        $this->createController()->deleteEntity($entityManager, new FormFieldTemplate()->setRestricted(false));
    }
}
