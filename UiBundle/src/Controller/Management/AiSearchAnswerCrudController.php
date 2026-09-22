<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management;

use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\AiSearchAnswer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// What visitors asked the site search and what it answered - above all what it found nothing for, which is the content the site lacks. Read-only by nature: the rows are written by the questions themselves (see AiSiteSearch), and the one thing to do with a row is to dismiss it
class AiSearchAnswerCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AiSearchAnswer::class;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('question')
                ->setLabel(t('label.ai_search_question', [], 'ui')),

            BooleanField::new('found')
                ->setLabel(t('label.ai_search_found', [], 'ui'))
                ->renderAsSwitch(false),

            TextareaField::new('answer')
                ->setLabel(t('label.ai_search_answer', [], 'ui'))
                ->setMaxLength(Crud::PAGE_INDEX === $pageName ? 120 : 10000),

            // The titles of the pages the answer was built from, the urls being on the detail page
            TextField::new('sources')
                ->setLabel(t('label.ai_search_sources', [], 'ui'))
                ->formatValue(fn (mixed $value, AiSearchAnswer $answer): string => implode(', ', array_map(
                    fn (array $source): string => Crud::PAGE_DETAIL === $pageName ? $source['title'] . ' (' . $source['url'] . ')' : $source['title'],
                    $answer->getSources(),
                ))),

            IntegerField::new('hitCount')
                ->setLabel(t('label.ai_search_hit_count', [], 'ui')),

            TextField::new('locale')
                ->setLabel(t('label.locale', [], 'ui'))
                ->hideOnIndex(),

            DateTimeField::new('updatedAt')
                ->setLabel(t('label.ai_search_updated_at', [], 'ui')),
        ];
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->setPermission(Action::INDEX, $this->configService->get('site-role-editor'))
            ->setPermission(Action::DETAIL, $this->configService->get('site-role-editor'))
            ->setPermission(Action::DELETE, $this->configService->get('site-role-admin'))
            // Written by the visitors' questions, never by hand
            ->disable(Action::NEW, Action::EDIT)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.ai_search_answer', [], 'ui'))
            ->setEntityLabelInPlural(t('label.ai_search_answers', [], 'ui'))
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-editor'))
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->overrideTemplate('crud/index', '@c975LUi/management/ai_search_answer_crud_index.html.twig')
        ;
    }

    // "Found" is what tells what the site answers from what it lacks
    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('question')
            ->add(BooleanFilter::new('found')->setLabel(t('label.ai_search_found', [], 'ui')))
        ;
    }
}
