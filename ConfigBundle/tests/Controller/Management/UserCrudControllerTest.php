<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller\Management;

use App\Entity\User;
use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Controller\Management\UserCrudController;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use c975L\ConfigBundle\Security\Voter\UserManagementVoter;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\TableExporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Provider\FieldProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserCrudControllerTest extends TestCase
{
    private const array AVAILABLE_ROLES = ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_EDITOR'];

    // What config/configs.json now ships: ROLE_SUPER_ADMIN is no longer declared there, the controller decides it
    private const array DEFAULT_ROLES = ['ROLE_ADMIN', 'ROLE_EDITOR'];

    // AbstractCrudController::configureFields() only ever calls getDefaultFields() on whatever the container returns for FieldProvider::class - the real one is final readonly, so an anonymous object with that single method stands in for it
    private function createContainer(array $defaultFields = []): Container
    {
        $container = new Container();
        $fieldProvider = new class {
            public array $defaultFields = [];

            public function getDefaultFields(string $pageName): iterable
            {
                return $this->defaultFields;
            }
        };
        $fieldProvider->defaultFields = $defaultFields;
        $container->set(FieldProvider::class, $fieldProvider);

        return $container;
    }

    private function createAdminContextProvider(?User $editedUser): AdminContextProvider
    {
        $requestStack = new RequestStack();

        if (null !== $editedUser) {
            $entityDto = new EntityDto(User::class, new ClassMetadata(User::class), null, $editedUser);
            $request = new Request();
            $request->attributes->set('easyadmin_context', AdminContext::forTesting(
                crudContext: CrudContext::forTesting(entityDto: $entityDto),
            ));
            $requestStack->push($request);
        }

        return new AdminContextProvider($requestStack);
    }

    private function createController(bool $actingUserIsSuperAdmin, ?User $editedUser = null, ?array $availableRoles = null, array $defaultFields = [], ?EventDispatcherInterface $eventDispatcher = null, ?User $actingUser = null): UserCrudController
    {
        $availableRoles ??= self::AVAILABLE_ROLES;

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $slug): mixed => 'user-roles-available' === $slug ? $availableRoles : 'ROLE_ADMIN',
        );

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => 'ROLE_SUPER_ADMIN' === $attribute ? $actingUserIsSuperAdmin : true,
        );
        $security->method('getUser')->willReturn($actingUser);

        // Like the real one, the generator carries the current query string over unless a parameter is unset
        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        foreach (['setController', 'setAction', 'setEntityId', 'set'] as $method) {
            $adminUrlGenerator->method($method)->willReturnSelf();
        }
        $tokenUnset = false;
        $adminUrlGenerator->method('unset')->willReturnCallback(static function (string $name) use (&$tokenUnset, $adminUrlGenerator): AdminUrlGeneratorInterface {
            $tokenUnset = $tokenUnset || 'token' === $name;

            return $adminUrlGenerator;
        });
        $adminUrlGenerator->method('generateUrl')->willReturnCallback(static function () use (&$tokenUnset): string {
            return '/management/user' . ($tokenUnset ? '' : '?token=token');
        });

        $controller = new UserCrudController(
            $configService,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(TableExporter::class),
            $security,
            $this->createStub(TranslatorInterface::class),
            $this->createAdminContextProvider($editedUser),
            $eventDispatcher ?? $this->createStub(EventDispatcherInterface::class),
            $adminUrlGenerator,
            $this->createStub(CsrfTokenManagerInterface::class),
        );
        $controller->setContainer($this->createContainer($defaultFields));

        return $controller;
    }

    private function createUser(array $roles, bool $isVerified = true, string $email = 'jane@example.com'): User
    {
        $user = new User();
        $user->setRoles($roles);
        $user->setIsVerified($isVerified);
        $user->setEmail($email);

        return $user;
    }

    // --- configureFields: isEnabled -----------------------------------------------------------------

    // An account nobody confirmed is deleted rather than disabled: unverified and disabled are the same state on a fresh sign-up, and the registration resend would hand such an account its way back in
    public function testTheEnabledFieldIsFrozenOnAnUnverifiedAccount(): void
    {
        $field = $this->enabledField($this->createController(true, $this->createUser(['ROLE_USER'], false), defaultFields: [BooleanField::new('isEnabled')]));

        $this->assertNotNull($field);
        $this->assertSame('disabled', $field->getAsDto()->getFormTypeOption('disabled'));
    }

    // Locking an account out without deleting it is what the field is there for, once the address is confirmed
    public function testTheEnabledFieldStaysEditableOnAVerifiedAccount(): void
    {
        $field = $this->enabledField($this->createController(true, $this->createUser(['ROLE_USER']), defaultFields: [BooleanField::new('isEnabled')]));

        $this->assertNotNull($field);
        $this->assertNull($field->getAsDto()->getFormTypeOption('disabled'));
    }

    private function enabledField(UserCrudController $controller): mixed
    {
        foreach ($controller->configureFields(Crud::PAGE_EDIT) as $field) {
            if ('isEnabled' === $field->getAsDto()->getProperty()) {
                return $field;
            }
        }

        return null;
    }

    private function rolesField(UserCrudController $controller): mixed
    {
        foreach ($controller->configureFields(Crud::PAGE_EDIT) as $field) {
            if ('roles' === $field->getAsDto()->getProperty()) {
                return $field;
            }
        }

        return null;
    }

    // --- configureFields: roles ---------------------------------------------------------------------------

    public function testRolesChoicesOfferEveryAvailableRoleToASuperAdmin(): void
    {
        $field = $this->rolesField($this->createController(true, $this->createUser(['ROLE_ADMIN'])));

        $this->assertSame(self::AVAILABLE_ROLES, array_keys($field->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES)));
    }

    // ROLE_SUPER_ADMIN out of the choices means out of the submitted form's allowed values too (Symfony's ChoiceType rejects anything else), so a plain ROLE_ADMIN can't grant it to anyone, themselves included
    public function testRolesChoicesHideSuperAdminFromALesserAdmin(): void
    {
        $field = $this->rolesField($this->createController(false, $this->createUser(['ROLE_ADMIN'])));

        $this->assertSame(['ROLE_ADMIN', 'ROLE_EDITOR'], array_values(array_keys($field->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES))));
    }

    public function testRolesFieldIsEditableWhenTheEditedUserIsNotASuperAdmin(): void
    {
        $field = $this->rolesField($this->createController(false, $this->createUser(['ROLE_ADMIN'])));

        $this->assertArrayNotHasKey('disabled', $field->getAsDto()->getFormTypeOptions());
    }

    // The mirror of the above: a role a lesser admin can't grant is one they can't take away either - without the disabled field, saving a super admin's record would submit a set the role isn't in, silently demoting them
    public function testRolesFieldIsFrozenForALesserAdminEditingASuperAdmin(): void
    {
        $field = $this->rolesField($this->createController(false, $this->createUser(['ROLE_SUPER_ADMIN'])));

        $this->assertSame('disabled', $field->getAsDto()->getFormTypeOptions()['disabled'] ?? null);
    }

    // Frozen doesn't mean hidden: nothing is submitted back from a disabled field, so the role stays in the choices for the select to show what the edited user actually has
    public function testRolesChoicesKeepSuperAdminOnAFrozenField(): void
    {
        $field = $this->rolesField($this->createController(false, $this->createUser(['ROLE_SUPER_ADMIN'])));

        $this->assertContains('ROLE_SUPER_ADMIN', array_keys($field->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES)));
    }

    // The shipped default no longer declares ROLE_SUPER_ADMIN (it's granted once by c975l:site:create), so a super admin would lose the ability to grant it if the choices were only what the config holds
    public function testRolesChoicesOfferSuperAdminToASuperAdminEvenWhenTheConfigOmitsIt(): void
    {
        $field = $this->rolesField($this->createController(true, $this->createUser(['ROLE_ADMIN']), self::DEFAULT_ROLES));

        $this->assertSame(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_EDITOR'], array_keys($field->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES)));
    }

    // Same omission, but on the frozen field of a super admin edited by a lesser admin: without the role in the choices the select would show nothing of what that user actually has
    public function testRolesChoicesKeepSuperAdminOnAFrozenFieldEvenWhenTheConfigOmitsIt(): void
    {
        $field = $this->rolesField($this->createController(false, $this->createUser(['ROLE_SUPER_ADMIN']), self::DEFAULT_ROLES));

        $this->assertContains('ROLE_SUPER_ADMIN', array_keys($field->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES)));
    }

    // A lesser admin gets no extra role from the omission either - the config's own content is what's left
    public function testRolesChoicesHideSuperAdminFromALesserAdminWhenTheConfigOmitsIt(): void
    {
        $field = $this->rolesField($this->createController(false, $this->createUser(['ROLE_ADMIN']), self::DEFAULT_ROLES));

        $this->assertSame(['ROLE_ADMIN', 'ROLE_EDITOR'], array_keys($field->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES)));
    }

    // A role the config stopped listing would be dropped from the select's values and taken away on the next save, exactly what the ROLE_SUPER_ADMIN guard prevents for its own role
    public function testRolesChoicesKeepARoleTheEditedUserHoldsButTheConfigOmits(): void
    {
        $field = $this->rolesField($this->createController(false, $this->createUser(['ROLE_ADMIN', 'ROLE_MODERATOR']), self::DEFAULT_ROLES));

        $this->assertSame(['ROLE_ADMIN', 'ROLE_EDITOR', 'ROLE_MODERATOR'], array_keys($field->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES)));
    }

    // Keeping it is not granting it: the role is only ever offered on the edit page of a user who already holds it
    public function testRolesChoicesDontOfferARoleOutsideTheConfigToAUserWithoutIt(): void
    {
        $field = $this->rolesField($this->createController(false, $this->createUser(['ROLE_ADMIN']), self::DEFAULT_ROLES));

        $this->assertNotContains('ROLE_MODERATOR', array_keys($field->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES)));
    }

    // A super admin acting on another super admin keeps a fully editable field - the guard is about lesser admins only
    public function testRolesFieldStaysEditableForASuperAdminEditingASuperAdmin(): void
    {
        $field = $this->rolesField($this->createController(true, $this->createUser(['ROLE_SUPER_ADMIN'])));

        $this->assertArrayNotHasKey('disabled', $field->getAsDto()->getFormTypeOptions());
    }

    // --- configureCrud ------------------------------------------------------------------------------------

    // EasyAdmin evaluates the entity permission per row: a plain role could only ever answer "any user", where the voter also keeps a lesser admin away from a super admin's own account, on every action
    public function testTheEntityPermissionIsDecidedByTheVoter(): void
    {
        $crud = $this->createController(false)->configureCrud(Crud::new());

        $this->assertSame(UserManagementVoter::MANAGE, $crud->getAsDto()->getEntityPermission());
    }

    // --- anonymize ----------------------------------------------------------------------------------------

    // The row button is only drawn where the action would run, the same rule the action checks again
    public function testTheAnonymizeActionIsOfferedOnAPlainAccountOnly(): void
    {
        $action = $this->anonymizeAction($this->createController(false, actingUser: $this->createUser(['ROLE_ADMIN'], email: 'admin@example.com')));
        $display = static fn (User $user): bool => $action->isDisplayed(new EntityDto(User::class, new ClassMetadata(User::class), null, $user));

        $this->assertTrue($display($this->createUser([])));
        $this->assertFalse($display($this->createUser([], email: 'anonymized-7@' . InactivityAwareInterface::ANONYMIZED_DOMAIN)));
        $this->assertFalse($display($this->createUser(['ROLE_SUPER_ADMIN'])));
        $this->assertFalse($display($this->createUser(['ROLE_ADMIN'], email: 'admin@example.com')));
    }

    // Same sequence as AccountDeleteController: the event carries the address the account held, dispatched before the flush
    public function testAnonymizeErasesTheAccountAndTellsTheSite(): void
    {
        $user = $this->createUser([]);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->with($this->callback(
            static fn (UserAnonymizedEvent $event): bool => $event->user === $user && 'jane@example.com' === $event->email,
        ));

        [$response, $session] = $this->runAnonymize($user, true, $eventDispatcher);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/management/user', $response->getTargetUrl());
        $this->assertStringEndsWith('@' . InactivityAwareInterface::ANONYMIZED_DOMAIN, (string) $user->getEmail());
        $this->assertFalse($user->isEnabled());
        $this->assertNotEmpty($session->getFlashBag()->get('success'));
    }

    // The token travels in the url of a GET, so a forged link fired from another page is refused
    public function testAnonymizeLeavesTheAccountAloneWithoutAValidToken(): void
    {
        $user = $this->createUser([]);

        $this->runAnonymize($user, false);

        $this->assertSame('jane@example.com', $user->getEmail());
    }

    // The owner's account is out of reach even through a crafted url, the button being hidden on its row
    public function testAnonymizeRefusesTheSuperAdminAccount(): void
    {
        $user = $this->createUser(['ROLE_SUPER_ADMIN']);

        $this->runAnonymize($user, true);

        $this->assertSame('jane@example.com', $user->getEmail());
    }

    private function anonymizeAction(UserCrudController $controller): mixed
    {
        $actions = $controller->configureActions(Actions::new()
            ->add(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DELETE));

        return $actions->getAsDto(Crud::PAGE_INDEX)->getAction(Crud::PAGE_INDEX, 'anonymize');
    }

    // --- deleteEntity -------------------------------------------------------------------------------------

    // A hard delete runs the listeners of an anonymization first: a foreign key in "SET NULL" would otherwise leave an unpaid basket orphaned and reachable by its recovery cookie
    public function testDeleteDetachesWhatTheAccountOwnsBeforeRemovingIt(): void
    {
        $user = $this->createUser(['ROLE_USER']);
        $calls = [];

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (object $event): bool => $event instanceof UserAnonymizedEvent && $user === $event->user && 'jane@example.com' === $event->email))
            ->willReturnCallback(static function (object $event) use (&$calls): object {
                $calls[] = 'dispatch';

                return $event;
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($user)->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'remove';
        });
        $entityManager->expects($this->once())->method('flush');

        $this->createController(true, eventDispatcher: $eventDispatcher)->deleteEntity($entityManager, $user);

        $this->assertSame(['dispatch', 'remove'], $calls);
    }

    // Runs the action on this account, the acting admin being granted everything but ROLE_SUPER_ADMIN; returns [Response, Session] for the flash
    private function runAnonymize(User $user, bool $validToken, ?EventDispatcherInterface $eventDispatcher = null): array
    {
        $controller = $this->createController(false, eventDispatcher: $eventDispatcher, actingUser: $this->createUser(['ROLE_ADMIN'], email: 'admin@example.com'));

        $session = new Session(new MockArraySessionStorage());
        $request = new Request(['token' => 'token']);
        $request->setSession($session);
        $requestStack = new RequestStack([$request]);

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn($validToken);

        $container = $this->createContainer();
        $container->set('security.authorization_checker', $authorizationChecker);
        $container->set('security.csrf.token_manager', $csrfTokenManager);
        $container->set('request_stack', $requestStack);
        $controller->setContainer($container);

        $context = AdminContext::forTesting(crudContext: CrudContext::forTesting(
            entityDto: new EntityDto(User::class, new ClassMetadata(User::class), null, $user),
        ));

        return [$controller->anonymize($context, $request), $session];
    }
}
