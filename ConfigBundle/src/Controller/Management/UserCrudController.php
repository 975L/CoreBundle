<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller\Management;

use App\Entity\User;
use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Security\Voter\UserManagementVoter;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class UserCrudController extends AbstractCrudController
{
    private const string ANONYMIZE_CSRF_TOKEN = 'user_anonymize';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TableExporter $tableExporter,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly AdminContextProvider $adminContextProvider,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    // Relies on EasyAdmin's auto-discovery of App\Entity\User's own fields (which vary per app), except for: the hashed password (excluded so it's never displayed or overwritten from the backoffice), creation/modification/lastLogin/inactivityNoticeSentAt (made readonly since they're set automatically), isVerified (made readonly since it must only be set by EmailVerifier upon email confirmation), isEnabled on an account nobody confirmed yet (see editsAnUnverifiedAccount()); "roles" is excluded by EasyAdmin's own auto-discovery (JSON columns are never auto-discovered), so it's added explicitly as a proper multiple-choice field
    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        foreach (parent::configureFields($pageName) as $field) {
            // A field may also be yielded as a bare property name, which carries none of the configuration read below
            if (!$field instanceof FieldInterface) {
                yield $field;

                continue;
            }

            $property = $field->getAsDto()->getProperty();

            if ('password' === $property) {
                continue;
            }

            if (in_array($property, ['creation', 'modification', 'isVerified', 'lastLogin', 'inactivityNoticeSentAt'], true)) {
                yield $field->setFormTypeOption('disabled', 'disabled');

                continue;
            }

            if ('isEnabled' === $property && $this->editsAnUnverifiedAccount()) {
                yield $field->setFormTypeOption('disabled', 'disabled');

                continue;
            }

            yield $field;
        }

        // ROLE_USER is excluded, it's already granted by default to every user (see User::getRoles()); not required, since having none selected simply means the user only has that default role
        $isFrozen = $this->editsASuperAdminWithoutBeingOne();
        $rolesField = ChoiceField::new('roles')
            ->setLabel(t('label.roles', [], 'config'))
            ->setChoices($this->roleChoices($isFrozen))
            ->allowMultipleChoices()
            ->renderExpanded(false)
            ->setRequired(false)
            // Marks the whole row for the guided project's highlight, TomSelect hiding the #User_roles select itself
            ->setFormTypeOption('row_attr', ['data-guided-user-roles' => '']);

        // A disabled field is ignored on submit and keeps its stored value, whatever gets posted
        if ($isFrozen) {
            $rolesField->setFormTypeOption('disabled', 'disabled');
        }

        yield $rolesField;
    }

    // An account nobody has confirmed is deleted, never disabled: disabled and unverified are the very same state on a fresh sign-up, so the two cannot be told apart afterwards - and RegisterFormAction, which sends a new confirmation link to any unverified address, would hand the account back its way in (see scaffold/src/Service/RegisterFormAction.php)
    private function editsAnUnverifiedAccount(): bool
    {
        $edited = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();

        return $edited instanceof User && !$edited->isVerified();
    }

    // A super admin's roles are shown to a lesser admin, but frozen. Symfony's ChoiceType silently drops a value missing from the choices when displaying the field (unlike on submit, where it rejects it), so without this the form would come back without ROLE_SUPER_ADMIN in the submitted set - and a plain ROLE_ADMIN saving a super admin's record would demote them without ever seeing the role. Second line of defence since UserManagementVoter: the edit page of a super admin's account is no longer reachable by a lesser admin at all, unless an app overrides configureCrud() and drops the entity permission with it
    private function editsASuperAdminWithoutBeingOne(): bool
    {
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return false;
        }

        return in_array('ROLE_SUPER_ADMIN', $this->editedRoles(), true);
    }

    // The roles the account being edited already holds, ROLE_USER excluded (User::getRoles() always adds it, and every user has it anyway). Empty on the "new" page, no role being stored yet
    private function editedRoles(): array
    {
        $edited = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();
        if (!$edited instanceof User) {
            return [];
        }

        return array_values(array_filter(
            $edited->getRoles(),
            static fn (string $role): bool => 'ROLE_USER' !== $role,
        ));
    }

    // Reads the extra roles selectable in the backoffice from the "user-roles-available" config (JSON array). ROLE_SUPER_ADMIN is decided here rather than read from the config, which no longer declares it by default (it's the owner's role, granted once by c975l:site:create): it's dropped from whatever the config holds, then put back only for an acting user who already holds it — otherwise a plain ROLE_ADMIN could grant it to themselves or anyone else and bypass ConfigBundle's "restricted" configs entirely. Out of the choices means out of the submitted form's allowed values too, Symfony's ChoiceType rejects anything outside them. Kept on a frozen field: nothing is submitted back from it, and dropping it there would just hide the role the edited user really has
    private function roleChoices(bool $keepSuperAdmin): array
    {
        $roles = array_values(array_filter(
            (array) $this->configService->get('user-roles-available'),
            static fn (string $role): bool => 'ROLE_SUPER_ADMIN' !== $role,
        ));

        // Same reasoning as ROLE_SUPER_ADMIN, for any other role: one the edited user holds while the config stopped listing it would be hidden on display and missing from the submitted set, taking it away on the next save without anyone seeing it. Adding it back to the choices doesn't let it be granted to someone who doesn't have it - it isn't offered on their own edit page
        foreach ($this->editedRoles() as $role) {
            if ('ROLE_SUPER_ADMIN' !== $role && !in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        }

        if ($keepSuperAdmin || $this->security->isGranted('ROLE_SUPER_ADMIN')) {
            array_unshift($roles, 'ROLE_SUPER_ADMIN');
        }

        return array_combine($roles, $roles);
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $exportGroup = ActionGroup::new('export', t('label.export', [], 'config'), 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->addAction(Action::new('exportSql', 'SQL')->linkToCrudAction('exportSql'))
            ->addAction(Action::new('exportCsv', 'CSV')->linkToCrudAction('exportCsv'))
            ->addAction(Action::new('exportJson', 'JSON')->linkToCrudAction('exportJson'))
        ;

        // Lets the admin back out of an edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        // Erases an account by hand (a bounced address, an erasure asked by email), exactly as its owner or c975l:config:users-cleanup would - built as a url rather than linked to the crud action, so the csrf token the action checks travels with it
        $anonymizeAction = Action::new('anonymize', t('action.anonymize', [], 'config'), 'fa fa-user-slash')
            ->linkToUrl(fn (object $user): string => $this->anonymizeUrl($user))
            ->displayIf(fn (object $user): bool => $this->isAnonymizable($user))
            ->askConfirmation(t('confirm.anonymize', [], 'config'))
            ->asDangerAction()
            ->addCssClass('btn btn-danger');

        return $actions
            ->disable(Action::NEW)
            ->disable(Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $exportGroup)
            ->add(Crud::PAGE_INDEX, $anonymizeAction)
            ->add(Crud::PAGE_EDIT, $anonymizeAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, 'anonymize', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.anonymize', [], 'config'),
            ))
            ->setPermission(Action::INDEX, $this->configService->get('site-role-admin'))
            ->setPermission(Action::EDIT, $this->configService->get('site-role-admin'))
            ->setPermission(Action::DELETE, $this->configService->get('site-role-admin'))
            ->setPermission('anonymize', $this->configService->get('site-role-admin'))
            ->setPermission('exportSql', 'ROLE_SUPER_ADMIN')
            ->setPermission('exportCsv', 'ROLE_SUPER_ADMIN')
            ->setPermission('exportJson', 'ROLE_SUPER_ADMIN')
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            // Per-row permission (see UserManagementVoter), which the plain "site-role-admin" role this used to hold couldn't express: it also keeps a lesser admin away from a super admin's own account, whatever the action
            ->setEntityPermission(UserManagementVoter::MANAGE)
            ->overrideTemplate('crud/index', '@c975LConfig/management/user_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LConfig/management/user_crud_edit.html.twig')
        ;
    }

    // Anonymizes the account with the very sequence of AccountDeleteController, so a listener detaching what it owns runs before the flush. The entity permission only hides the row button, the voter is asked again here
    #[AdminRoute('/{entityId}/anonymize')]
    public function anonymize(AdminContext $context, Request $request): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        $user = $context->getEntity()->getInstance();
        $this->denyAccessUnlessGranted(UserManagementVoter::MANAGE, $user);

        if (!$this->isCsrfTokenValid(self::ANONYMIZE_CSRF_TOKEN, $request->query->getString('token')) || !$this->isAnonymizable($user)) {
            return $this->redirect($this->indexUrl());
        }

        $email = $user->getEmail();
        $user->anonymize();
        $this->eventDispatcher->dispatch(new UserAnonymizedEvent($user, $email));
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.user_anonymized', [], 'config'));

        return $this->redirect($this->indexUrl());
    }

    // The DELETE action and its batch twin: the listeners of an anonymization detach what the account owns first, as the foreign keys pointing at it would otherwise either block the removal or leave that data orphaned
    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        if ($entityInstance instanceof InactivityAwareInterface) {
            $this->eventDispatcher->dispatch(new UserAnonymizedEvent($entityInstance, $entityInstance->getEmail()));
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    // Offered on an account the site can anonymize and that isn't already: never the owner's (nobody left could hand ROLE_SUPER_ADMIN back, as in AccountDeleteController), nor the acting admin's own, which goes through the account page
    private function isAnonymizable(mixed $user): bool
    {
        return $user instanceof InactivityAwareInterface
            && !str_ends_with((string) $user->getEmail(), '@' . InactivityAwareInterface::ANONYMIZED_DOMAIN)
            && !in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)
            && $user->getUserIdentifier() !== $this->security->getUser()?->getUserIdentifier();
    }

    // The url of the row button, its csrf token in the query string - the action is a GET, which an <img> on a third-party page would otherwise fire on a logged-in admin
    private function anonymizeUrl(object $user): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('anonymize')
            ->setEntityId($user->getId())
            ->set('token', $this->csrfTokenManager->getToken(self::ANONYMIZE_CSRF_TOKEN)->getValue())
            ->generateUrl();
    }

    // The users listing the action comes back to, whether it ran or was refused
    private function indexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->unset('token')
            ->generateUrl();
    }

    #[AdminRoute]
    public function exportSql(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->tableExporter->export(ExportFormat::Sql, $this->getUserTableName(), $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportCsv(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->tableExporter->export(ExportFormat::Csv, $this->getUserTableName(), $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportJson(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->tableExporter->export(ExportFormat::Json, $this->getUserTableName(), $this->fetchExportRows());
    }

    // The hashed password is never exported, exposing it brings no legitimate use and only adds risk
    private function fetchExportRows(): array
    {
        $rows = $this->entityManager->getConnection()
            ->fetchAllAssociative("SELECT * FROM `{$this->getUserTableName()}`");

        return array_map(static fn (array $row): array => array_diff_key($row, ['password' => null]), $rows);
    }

    private function getUserTableName(): string
    {
        return $this->entityManager->getClassMetadata(User::class)->getTableName();
    }
}
