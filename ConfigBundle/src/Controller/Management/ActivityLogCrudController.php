<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller\Management;

use c975L\ConfigBundle\Entity\ActivityLog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// Who did what and when in the back office. Read-only with no way to delete a row, an account's own traces being none of its business to erase: only the retention empties it (see ActivityLogCleanupCommand)
class ActivityLogCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // The entity listed here
    public static function getEntityFqcn(): string
    {
        return ActivityLog::class;
    }

    // One column per thing a row holds
    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            DateTimeField::new('occurredAt')
                ->setLabel(t('label.activity_log_occurred_at', [], 'config')),

            AssociationField::new('user')
                ->setLabel(t('label.activity_log_user', [], 'config'))
                ->setSortable(false),

            TextField::new('action')
                ->setLabel(t('label.activity_log_action', [], 'config'))
                ->formatValue(fn (?string $action): string => $this->translator->trans('label.activity_log_action_' . $action, [], 'config')),

            TextField::new('entityType')
                ->setLabel(t('label.activity_log_entity_type', [], 'config')),

            // The id as a fallback: an account is never named here, and an entity without __toString() has nothing else. Escaped as EasyAdmin prints whatever formatValue() returns as HTML, and a label can come from what a visitor typed
            TextField::new('label')
                ->setLabel(t('label.activity_log_label', [], 'config'))
                ->formatValue(static fn (?string $label, ActivityLog $log): string => htmlspecialchars($label ?? '#' . $log->getEntityId(), \ENT_NOQUOTES)),

            // An ArrayField whatever is asked for, EasyAdmin swapping a plain Field for the one its JSON column calls for, and printing the values rather than the names
            ArrayField::new('changes')
                ->setLabel(t('label.activity_log_changes', [], 'config'))
                ->setSortable(false)
                ->setTemplatePath('@c975LConfig/management/activity_log_changes.html.twig'),

            TextField::new('path')
                ->setLabel(t('label.activity_log_path', [], 'config')),
        ];
    }

    // Read-only and for admins
    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // Admin rather than editor: this screen tells what each colleague did
            ->setPermission(Action::INDEX, $this->configService->get('site-role-admin'))
            // Every column is on the list, and a row is neither written nor erased by hand
            ->disable(Action::NEW, Action::EDIT, Action::DETAIL, Action::DELETE, Action::BATCH_DELETE)
        ;
    }

    // The latest row first, under the screen's own template
    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.activity_log', [], 'config'))
            ->setEntityLabelInPlural(t('label.activity_logs', [], 'config'))
            ->setEntityPermission($this->configService->get('site-role-admin'))
            // The id behind it, as the rows of one save share their date
            ->setDefaultSort(['occurredAt' => 'DESC', 'id' => 'DESC'])
            ->overrideTemplate('crud/index', '@c975LConfig/management/activity_log_crud_index.html.twig')
        ;
    }

    // Finding a row by date, account, action, type or name
    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        $actions = [];
        foreach (ActivityLog::ACTIONS as $action) {
            $actions[$this->translator->trans('label.activity_log_action_' . $action, [], 'config')] = $action;
        }

        return $filters
            ->add(DateTimeFilter::new('occurredAt')->setLabel(t('label.activity_log_occurred_at', [], 'config')))
            // Only the accounts holding a row: a site's members can run to thousands, and none of them has done anything here
            ->add(EntityFilter::new('user')
                ->setLabel(t('label.activity_log_user', [], 'config'))
                ->setFormTypeOption('value_type_options.choice_label', 'userIdentifier')
                ->setFormTypeOption('value_type_options.query_builder', static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('account')
                    ->where(sprintf('account.id IN (SELECT IDENTITY(log.user) FROM %s log)', ActivityLog::class))
                    ->orderBy('account.id')))
            ->add(ChoiceFilter::new('action')->setLabel(t('label.activity_log_action', [], 'config'))->setChoices($actions))
            ->add(TextFilter::new('entityType')->setLabel(t('label.activity_log_entity_type', [], 'config')))
            ->add(TextFilter::new('label')->setLabel(t('label.activity_log_label', [], 'config')))
        ;
    }
}
