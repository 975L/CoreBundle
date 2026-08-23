<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Controller\Management\NotFoundCrudController;
use c975L\ConfigBundle\Controller\Management\RedirectCrudController;
use c975L\ConfigBundle\Entity\NotFound;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Provider\FieldProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotFoundCrudControllerTest extends TestCase
{
    // The roles configs.json declares, each config answering with its own slug so a test can tell which one a permission came from
    private function createConfigService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $slug): string => 'site-role-admin' === $slug ? 'ROLE_ADMIN' : 'ROLE_EDITOR'
        );

        return $configService;
    }

    private function createController(?AdminUrlGeneratorInterface $adminUrlGenerator = null): NotFoundCrudController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new NotFoundCrudController(
            $this->createConfigService(),
            $adminUrlGenerator ?? $this->createStub(AdminUrlGeneratorInterface::class),
            $translator,
        );
    }

    // AbstractCrudController::configureFields() only ever calls getDefaultFields() on whatever the container returns for FieldProvider::class
    private function createFieldContainer(): Container
    {
        $container = new Container();
        $container->set(FieldProvider::class, new class {
            public function getDefaultFields(string $pageName): iterable
            {
                return [];
            }
        });

        return $container;
    }

    // A real EasyAdmin runtime pre-populates the default actions before calling configureActions(), which update() then assumes are there
    private function configureActions(NotFoundCrudController $controller): Actions
    {
        return $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );
    }

    public function testGetEntityFqcnIsTheNotFoundEntity(): void
    {
        $this->assertSame(NotFound::class, NotFoundCrudController::getEntityFqcn());
    }

    // What a row has to say to be acted on: the dead url, the page still linking to it, whether that page is ours, how often, and when last
    public function testConfigureFieldsExposesTheColumnsARowIsTriagedOn(): void
    {
        $controller = $this->createController();
        $controller->setContainer($this->createFieldContainer());

        $properties = [];
        foreach ($controller->configureFields(Crud::PAGE_INDEX) as $field) {
            $properties[] = $field->getAsDto()->getProperty();
        }

        $this->assertSame(['path', 'referer', 'internal', 'hits', 'lastSeen'], $properties);
    }

    // Nobody writes a 404, the requests do: creating or editing a row by hand would only ever produce a listing that says something the site never answered
    public function testConfigureActionsDisablesEverySortOfWriting(): void
    {
        $disabled = $this->configureActions($this->createController())->getAsDto(null)->getDisabledActions();

        $this->assertContains(Action::NEW, $disabled);
        $this->assertContains(Action::EDIT, $disabled);
        $this->assertContains(Action::DETAIL, $disabled);
    }

    // Same bar as the redirects the screen leads to: a link that broke is the editor's business, dismissing the row an admin's
    public function testConfigureActionsOpensTheScreenToAnEditorAndKeepsDeletionForAnAdmin(): void
    {
        $permissions = $this->configureActions($this->createController())->getAsDto(null)->getActionPermissions();

        $this->assertSame('ROLE_EDITOR', $permissions[Action::INDEX] ?? null);
        $this->assertSame('ROLE_EDITOR', $permissions['createRedirect'] ?? null);
        $this->assertSame('ROLE_ADMIN', $permissions[Action::DELETE] ?? null);
    }

    // The one action of the screen: it opens the redirect form on the very path the row holds (see RedirectCrudController::createEntity())
    public function testCreateRedirectActionPointsAtANewRedirectOnThatPath(): void
    {
        $generator = $this->createMock(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->expects($this->once())->method('setController')->with(RedirectCrudController::class)->willReturnSelf();
        $generator->expects($this->once())->method('setAction')->with(Action::NEW)->willReturnSelf();
        $generator->expects($this->once())->method('set')->with('fromPath', '/histoire/disparue')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/management?crudAction=new');

        $action = $this->configureActions($this->createController($generator))
            ->getAsDto(Crud::PAGE_INDEX)
            ->getAction(Crud::PAGE_INDEX, 'createRedirect')
        ;

        $url = $action->getUrl();
        $this->assertIsCallable($url);
        $this->assertSame('/management?crudAction=new', $url(new NotFound()->setPath('/histoire/disparue')));
    }

    // Index row actions become icon-only (see EasyAdminActionHelper::toIconOnly()), the label moving to the hover "title"
    public function testConfigureActionsSetsTheIndexRowActionsIconOnly(): void
    {
        $actionConfigDto = $this->configureActions($this->createController())->getAsDto(Crud::PAGE_INDEX);

        $this->assertFalse($actionConfigDto->getAction(Crud::PAGE_INDEX, Action::DELETE)->getLabel());
    }

    // The most recently followed broken link is the one that matters, a row nothing has hit for weeks being on its way out anyway
    public function testConfigureCrudSortsByTheLastRequestAndGivesTheEntityTheEditorPermission(): void
    {
        $crud = $this->createController()->configureCrud(Crud::new())->getAsDto();

        $this->assertSame(['lastSeen' => 'DESC'], $crud->getDefaultSort());
        $this->assertSame('ROLE_EDITOR', $crud->getEntityPermission());
    }

    // Internal and external rows are not fixed the same way - the first by editing the page carrying the link, the second by a redirect
    public function testConfigureFiltersOffersTheTwoUrlsAndTheInternalFlag(): void
    {
        $filters = $this->createController()->configureFilters(Filters::new());

        $this->assertSame(['path', 'referer', 'internal'], array_keys($filters->getAsDto()->all()));
    }
}
