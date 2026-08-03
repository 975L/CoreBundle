<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use c975L\UiBundle\Repository\FormRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Seeds the restricted Form/EmailTemplate rows a bundle needs to have working out of the box - lives here rather than in whichever bundle declares them (SiteBundle's "contact", ConfigBundle's "register"/"reset_password_request") so each one only carries its own field/block definitions, not the persistence machinery. Never flushes: the caller decides when, so a batch of seeds is one transaction
class FormSeeder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormRepository $formRepository,
        private readonly EmailTemplateRepository $emailTemplateRepository,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
    ) {
    }

    /**
     * Idempotent - seeds a restricted Form (name/fields locked, label/placeholder/order still editable, see
     * FormCrudController) so a Block referencing it works right away. $action names the FormActionInterface key
     * processing a submission (SendEmailFormAction for "contact", the scaffolded RegisterFormAction /
     * ResetPasswordRequestFormAction for the other two). A Form seeded by an earlier version of the bundle that
     * declares it (e.g. before it gained its own action, or before FormField gained "url") is backfilled in place
     * instead of left stale. Only touches a field's "url" when it's still null (its seeded default) - once an admin
     * has edited it (blank or otherwise), that edit is never overwritten, the Form's own "links" following the same rule.
     *
     * @param array<string, array<string, array{0: string, 1: string, 2: ?string}>> $coreFieldsByLocale locale => field name => [type, label, url], the locale actually used being kernel.default_locale (falling back on "en"): Form::$name is unique site-wide, so there is one "contact" Form, not one per locale
     * @param array<string, list<array{label: string, url: string}>>                $linksByLocale      locale => the links shown under the submit button (see Form::getLinks()), same locale resolution as the fields above
     */
    public function ensureForm(string $name, array $coreFieldsByLocale, ?string $action = null, ?array $actionConfig = null, array $linksByLocale = []): void
    {
        $fields = $this->forLocale($coreFieldsByLocale);

        // Merged into the action config rather than passed around on its own, that being where a Form keeps them
        $links = $this->forLocale($linksByLocale);
        if ([] !== $links) {
            $actionConfig = array_merge($actionConfig ?? [], ['links' => $links]);
        }

        $existing = $this->formRepository->findOneBy(['name' => $name]);
        if (null !== $existing) {
            $this->backfillForm($existing, $fields, $action, $actionConfig);

            return;
        }

        $this->entityManager->persist($this->buildForm($name, $fields, $action, $actionConfig));
    }

    // kernel.default_locale's own wording, falling back on "en" for a locale the caller ships none for
    private function forLocale(array $byLocale): array
    {
        return $byLocale[$this->defaultLocale] ?? $byLocale['en'] ?? [];
    }

    /**
     * Idempotent, seeding a restricted EmailTemplate whose name is locked but whose blocks stay editable.
     *
     * @param array<string, array<int, array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}>> $blocksByLocale locale => list of [type, heading, level, content, label, url] tuples, unused positions left null
     */
    public function ensureEmailTemplate(string $name, array $blocksByLocale): void
    {
        if (null !== $this->emailTemplateRepository->findOneBy(['name' => $name])) {
            return;
        }

        $blocks = $this->forLocale($blocksByLocale);

        $emailTemplate = (new EmailTemplate())
            ->setName($name)
            ->setRestricted(true);

        $position = 0;
        foreach ($blocks as [$type, $heading, $level, $content, $label, $url]) {
            $emailTemplate->addBlock(
                (new EmailBlock())
                    ->setType($type)
                    ->setHeading($heading)
                    ->setLevel($level)
                    ->setContent($content)
                    ->setLabel($label)
                    ->setUrl($url)
                    ->setPosition($position++)
            );
        }

        $this->entityManager->persist($emailTemplate);
    }

    // A Form seeded by an earlier version brought up to date in place - only ever on a still-restricted Form/field, so nothing an admin has taken over is touched
    private function backfillForm(Form $form, array $fields, ?string $action, ?array $actionConfig): void
    {
        if ($form->isRestricted() && $form->getAction() !== $action) {
            $form->setAction($action);
            // The rest of the config belongs to the action that just changed, so replacing it is the point - "links" doesn't: they are the Form's own, edited through their own collection editor, and follow the same never-overwrite rule as below rather than being reverted by a renamed action
            $links = $form->getActionConfig()['links'] ?? null;
            $form->setActionConfig(null === $links ? $actionConfig : array_merge($actionConfig ?? [], ['links' => $links]));
            $this->entityManager->persist($form);
        }

        // Backfilled on its own, same rule as a field's "url" below: a Form seeded before it declared any link gets them, one whose links an admin has already edited (emptied or otherwise) keeps that version
        if ($form->isRestricted() && isset($actionConfig['links']) && !array_key_exists('links', $form->getActionConfig() ?? [])) {
            $form->setLinks($actionConfig['links']);
            $this->entityManager->persist($form);
        }

        foreach ($form->getFields() as $field) {
            $url = $fields[$field->getName()][2] ?? null;
            if ($field->isRestricted() && null === $field->getUrl() && null !== $url) {
                $field->setUrl($url);
                $this->entityManager->persist($field);
            }
        }
    }

    // $fields entries are [type, label, url] tuples, in the order they're declared
    private function buildForm(string $name, array $fields, ?string $action, ?array $actionConfig): Form
    {
        $form = (new Form())
            ->setName($name)
            ->setAction($action)
            ->setRestricted(true)
            ->setActionConfig($actionConfig);

        $position = 0;
        foreach ($fields as $fieldName => [$type, $label, $url]) {
            $form->addField(
                (new FormField())
                    ->setName($fieldName)
                    ->setLabel($label)
                    ->setType($type)
                    ->setUrl($url)
                    ->setRequired(true)
                    ->setPosition($position++)
                    ->setRestricted(true)
            );
        }

        return $form;
    }
}
