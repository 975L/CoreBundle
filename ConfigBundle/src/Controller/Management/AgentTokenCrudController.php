<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller\Management;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Entity\AgentToken;
use c975L\ConfigBundle\Mcp\McpServer;
use c975L\ConfigBundle\Service\AgentTokenManager;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// The account's own agent tokens: one made for an agent shows its secret once, a deleted one is revoked; nobody sees another account's tokens
class AgentTokenCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly AgentTokenManager $agentTokenManager,
        private readonly McpServer $mcpServer,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AgentToken::class;
    }

    // A new token belongs to the account making it
    #[\Override]
    public function createEntity(string $entityFqcn): AgentToken
    {
        $user = $this->getUser();

        return new AgentToken()->setUser($user instanceof UserInterface ? $user : null);
    }

    // The secret drawn when the token is saved, shown once in a flash
    #[\Override]
    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $token = $entityInstance instanceof AgentToken ? $this->agentTokenManager->issue($entityInstance) : null;
        parent::persistEntity($entityManager, $entityInstance);
        // EasyAdmin renders a flash raw, so the name typed is escaped
        if (null !== $token) {
            $this->addFlash('success', $this->translator->trans('flash.agent_token_created', ['%name%' => htmlspecialchars($entityInstance->getName()), '%token%' => $token], 'config'));
        }
    }

    // Only a token of the account's own is revoked, an address made up for another one refused
    #[\Override]
    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if (!$entityInstance instanceof AgentToken || $entityInstance->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        parent::deleteEntity($entityManager, $entityInstance);
    }

    // Only the account's own tokens are listed
    #[\Override]
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.user = :user')
            ->setParameter('user', $this->getUser());
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        $keys = $this->mcpServer->keys();

        return [
            TextField::new('name')
                ->setLabel(t('label.agent_token_name', [], 'config')),
            BooleanField::new('writable')
                ->setLabel(t('label.agent_token_writable', [], 'config'))
                ->renderAsSwitch(false),
            ChoiceField::new('bundles')
                ->setLabel(t('label.agent_token_bundles', [], 'config'))
                ->setChoices(array_combine($keys, $keys))
                ->allowMultipleChoices()
                ->setRequired(false)
                ->setHelp(t('label.info_agent_token_bundles', [], 'config'))
                // Marks the whole row for the guided project's highlight, TomSelect hiding the #AgentToken_bundles select itself
                ->setFormTypeOption('row_attr', ['data-guided-agent-token-bundles' => '']),
            DateField::new('expiration')
                ->setLabel(t('label.agent_token_expiration', [], 'config')),
            DateTimeField::new('lastUse')
                ->setLabel(t('label.agent_token_last_use', [], 'config'))
                ->hideOnForm(),
        ];
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-contributor');

        return $actions
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::DELETE, $role)
            // A token's reach is set once: another reach is another token
            ->disable(Action::EDIT, Action::DETAIL)
        ;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.agent_token', [], 'config'))
            ->setEntityLabelInPlural(t('label.agent_tokens', [], 'config'))
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-contributor'))
            ->setDefaultSort(['creation' => 'DESC'])
            ->setHelp(Crud::PAGE_INDEX, t('label.info_agent_token', [], 'config'))
            ->setHelp(Crud::PAGE_NEW, t('label.info_agent_token', [], 'config'))
        ;
    }
}
