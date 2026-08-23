<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller\Management;

use c975L\ConfigBundle\Entity\NotFound;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// The broken links the site was actually asked for, listed so each one can be answered with a redirect. Read-only by nature: nobody writes a 404, the requests do (see NotFoundSubscriber), and the two things to do with a row are to redirect its path or to dismiss it
class NotFoundCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return NotFound::class;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('path')
                ->setLabel(t('label.not_found_path', [], 'config')),

            // A link rather than a string: the page still carrying the dead link is the one to open, and on an internal referer it is a page of this very site
            UrlField::new('referer')
                ->setLabel(t('label.not_found_referer', [], 'config')),

            BooleanField::new('internal')
                ->setLabel(t('label.not_found_internal', [], 'config'))
                ->renderAsSwitch(false),

            IntegerField::new('hits')
                ->setLabel(t('label.not_found_hits', [], 'config')),

            DateTimeField::new('lastSeen')
                ->setLabel(t('label.not_found_last_seen', [], 'config')),
        ];
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        // Sends the admin to a new redirect with the dead path already filled in (see RedirectCrudController::createEntity()) - the destination is the one decision left, and it is theirs
        $createRedirect = Action::new('createRedirect', t('label.create_redirect', [], 'config'), 'fa fa-arrow-right')
            ->linkToUrl(fn (NotFound $notFound): string => $this->adminUrlGenerator
                ->unsetAll()
                ->setController(RedirectCrudController::class)
                ->setAction(Action::NEW)
                ->set('fromPath', (string) $notFound->getPath())
                ->generateUrl())
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, $createRedirect)
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->setPermission(Action::INDEX, $this->configService->get('site-role-editor'))
            ->setPermission('createRedirect', $this->configService->get('site-role-editor'))
            // Dismissing a row says the link is dealt with, the same call as removing the redirect that answers it
            ->setPermission(Action::DELETE, $this->configService->get('site-role-admin'))
            // Rows are written by the requests themselves, never by hand, and a row holds nothing a listing does not already show
            ->disable(Action::NEW, Action::EDIT, Action::DETAIL)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-editor'))
            // The link that broke most recently is the one that matters, a row nothing has hit for weeks being on its way out anyway (see NotFoundCleanupCommand)
            ->setDefaultSort(['lastSeen' => 'DESC'])
            ->overrideTemplate('crud/index', '@c975LConfig/management/not_found_crud_index.html.twig')
        ;
    }

    // Internal is what tells the site's own broken links from the stale ones other sites publish, and they are not fixed the same way - the first by editing the page carrying the link, the second by a redirect
    #[\Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('path')
            ->add('referer')
            ->add(BooleanFilter::new('internal')->setLabel(t('label.not_found_internal', [], 'config')))
        ;
    }
}
