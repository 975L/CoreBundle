<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Command;

use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormFieldTemplate;
use c975L\UiBundle\Repository\FormFieldTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Seeds a starter FormFieldTemplate catalog - idempotent, matched by "name": an app that already ran this once, or an admin who edited/deleted a seeded row since, is never overwritten. Also run by SiteBundle's "c975l:site:create" wizard (same pattern as its own "c975l:config:load-all" call), so every new site starts with this catalog already filled in. No RGPD consent template here: every generic Form already gets its own sitewide consent checkbox for free (see FormSubmissionType's "gdpr" field, config-driven via "site-form-gdpr") - a template for it would just invite a confusing second checkbox
#[AsCommand(name: 'c975l:ui:form-field-template:import-defaults', description: 'Imports default FormFieldTemplate rows')]
class FormFieldTemplateImportDefaultsCommand extends Command
{
    private const DEFAULTS = [
        [
            'name' => 'name',
            'fieldLabel' => 'Nom',
            'placeholder' => null,
            'type' => FormField::TYPE_TEXT,
            'required' => true,
        ],
        [
            'name' => 'email',
            'fieldLabel' => 'Email',
            'placeholder' => null,
            'type' => FormField::TYPE_EMAIL,
            'required' => true,
        ],
        [
            'name' => 'phone',
            'fieldLabel' => 'Téléphone',
            'placeholder' => null,
            'type' => FormField::TYPE_TEL,
            'required' => false,
        ],
        [
            'name' => 'subject',
            'fieldLabel' => 'Sujet',
            'placeholder' => null,
            'type' => FormField::TYPE_TEXT,
            'required' => false,
        ],
        [
            'name' => 'message',
            'fieldLabel' => 'Message',
            'placeholder' => null,
            'type' => FormField::TYPE_TEXTAREA,
            'required' => true,
        ],
        [
            'name' => 'company',
            'fieldLabel' => 'Société',
            'placeholder' => null,
            'type' => FormField::TYPE_TEXT,
            'required' => false,
        ],
        [
            'name' => 'website',
            'fieldLabel' => 'Site web',
            'placeholder' => null,
            'type' => FormField::TYPE_URL,
            'required' => false,
        ],
        [
            'name' => 'cgu',
            'fieldLabel' => 'J\'accepte les conditions générales d\'utilisation',
            'placeholder' => null,
            'type' => FormField::TYPE_CHECKBOX,
            'required' => true,
        ],
        [
            'name' => 'newsletter',
            'fieldLabel' => 'Je souhaite recevoir la newsletter',
            'placeholder' => null,
            'type' => FormField::TYPE_CHECKBOX,
            'required' => false,
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFieldTemplateRepository $repository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $created = 0;

        foreach (self::DEFAULTS as $default) {
            if (null !== $this->repository->findOneBy(['name' => $default['name']])) {
                continue;
            }

            $template = (new FormFieldTemplate())
                ->setName($default['name'])
                ->setFieldLabel($default['fieldLabel'])
                ->setPlaceholder($default['placeholder'])
                ->setType($default['type'])
                ->setRequired($default['required'])
                ->setRestricted(true);
            $this->entityManager->persist($template);
            ++$created;
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        $io->success(\sprintf('%d modèle(s) de champ créé(s), %d déjà existant(s).', $created, \count(self::DEFAULTS) - $created));

        return Command::SUCCESS;
    }
}
