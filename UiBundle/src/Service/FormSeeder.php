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
    // What this run has already queued, which the repository cannot see before the caller's flush: a batch naming the same form twice would otherwise write it twice and break on the unique index
    /** @var array<string, Form> */
    private array $queuedForms = [];

    /** @var array<string, EmailTemplate> */
    private array $queuedTemplates = [];

    // The fields this run has just created, with the words the caller shipped for the other languages: they have no id before the caller's flush, so their translations are written after it (see SeededTranslationWriteListener)
    /** @var list<array{0: FormField, 1: string, 2: array<string, string|null>}> */
    private array $queuedTranslations = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormRepository $formRepository,
        private readonly EmailTemplateRepository $emailTemplateRepository,
        private readonly EmailTemplateFactory $emailTemplateFactory,
        private readonly FormTranslator $formTranslator,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
        /** @var string[] */
        #[Autowire(param: 'kernel.enabled_locales')]
        private readonly array $enabledLocales = [],
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

        $existing = $this->formRepository->findOneBy(['name' => $name]) ?? $this->queuedForms[$name] ?? null;
        if (null !== $existing) {
            $this->backfillForm($existing, $fields, $action, $actionConfig);

            return;
        }

        $this->entityManager->persist($this->queuedForms[$name] = $this->buildForm($name, $fields, $action, $actionConfig));
        $this->queueTranslations($this->queuedForms[$name], $coreFieldsByLocale);
    }

    /**
     * Keeps the other languages' wording of a form just created, to be written once it has an id.
     *
     * Only ever for a form this run has built: a form already in place has been through the back-office since, and its
     * translations are an admin's to write - the same rule the backfills above follow. A language the caller ships no
     * words for is left untouched rather than given the default one's, which would read as a translation nobody wrote.
     *
     * @param array<string, array<string, array{0: string, 1: string, 2: ?string}>> $coreFieldsByLocale
     */
    private function queueTranslations(Form $form, array $coreFieldsByLocale): void
    {
        foreach ($this->formTranslator->getTranslatableLocales() as $locale) {
            $translated = $coreFieldsByLocale[$locale] ?? [];
            if ([] === $translated) {
                continue;
            }

            foreach ($form->getFields() as $field) {
                $label = $translated[(string) $field->getName()][1] ?? null;
                if (null !== $label && $label !== $field->getLabel()) {
                    $this->queuedTranslations[] = [$field, $locale, ['label' => $label]];
                }
            }
        }
    }

    /**
     * Writes what queueTranslations() kept, the fields now carrying the ids they were waiting for.
     *
     * Emptied on the way out, so a second flush writes nothing twice.
     */
    public function writeQueuedTranslations(): void
    {
        $queued = $this->queuedTranslations;
        $this->queuedTranslations = [];

        foreach ($queued as [$field, $locale, $values]) {
            $this->formTranslator->store($field, $locale, $values);
        }
    }

    // kernel.default_locale's own wording, falling back on "en" for a locale the caller ships none for
    private function forLocale(array $byLocale): array
    {
        return $byLocale[$this->defaultLocale] ?? $byLocale['en'] ?? [];
    }

    /**
     * Idempotent, seeding one restricted EmailTemplate per language the site answers in - name locked, blocks editable.
     *
     * One row per locale and not one row with translated blocks: an e-mail is composed in the back-office as the
     * thing it is read as, so the person writing the German one edits German text, and a language nobody writes
     * simply has no row (see EmailTemplateRepository::findForRendering for what is sent then).
     *
     * A template written before e-mails carried a language is adopted rather than duplicated: the site's own row is
     * that very one, given the locale it was always read in (see adoptLocaleless() - c975l:ui:email-templates:ensure
     * does the same for every row at once, and neither has to run before the other).
     *
     * The site's own language always gets its row, even when the caller ships no blocks for it and none in English:
     * an empty template is one an admin can fill, whereas no template at all is an e-mail nobody can edit - it is
     * still sent, EmailTemplateRenderer::renderNamed() falling back on the declaration this was seeded from, but
     * the back-office has nothing to show for it. The other languages are seeded only where the caller actually
     * wrote them - never with somebody else's words.
     *
     * @param array<string, array<int, array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}>> $blocksByLocale locale => list of [type, heading, level, content, label, url] tuples, unused positions left null
     *
     * @return int how many data blocks were backfilled into templates already in place - reported by the command, a
     *             block appearing in a site's e-mail being something its admin should read about rather than discover
     */
    public function ensureEmailTemplate(string $name, array $blocksByLocale): int
    {
        $backfilled = 0;

        foreach (array_unique([$this->defaultLocale, ...$this->enabledLocales]) as $locale) {
            $isDefault = $locale === $this->defaultLocale;
            $blocks = $this->blocksFor($blocksByLocale, $locale, $isDefault);
            if (!$isDefault && [] === $blocks) {
                continue;
            }

            $emailTemplate = $this->existingEmailTemplate($name, $locale, $isDefault);

            if ($emailTemplate instanceof EmailTemplate) {
                $backfilled += $this->backfillEmailTemplate($emailTemplate, $blocks);

                continue;
            }

            $this->entityManager->persist(
                $this->queuedTemplates[$name . '|' . $locale] = $this->emailTemplateFactory->build($name, $locale, $blocks),
            );
        }

        return $backfilled;
    }

    /**
     * The wording a locale is seeded with: its own, or - for the site's own language alone - the English the declaration is written in.
     *
     * @param array<string, array<int, array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}>> $blocksByLocale
     *
     * @return array<int, array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}>
     */
    private function blocksFor(array $blocksByLocale, string $locale, bool $isDefault): array
    {
        return $blocksByLocale[$locale] ?? ($isDefault ? ($blocksByLocale['en'] ?? []) : []);
    }

    // The row this run would fill rather than create: one already in place, one it has queued itself, or - for the site's own language - one written before e-mails had a language
    private function existingEmailTemplate(string $name, string $locale, bool $isDefault): ?EmailTemplate
    {
        return $this->emailTemplateRepository->findOneBy(['name' => $name, 'locale' => $locale])
            ?? $this->queuedTemplates[$name . '|' . $locale] ?? ($isDefault ? $this->adoptLocaleless($name) : null);
    }

    // A template written before e-mails had a language, given the site's own rather than left beside a duplicate of itself. Only looked up when the site's locale has no row of its own, so the collision c975l:ui:email-templates:ensure warns about (both versions in place, one name for the two) can't be reached from here. The row is managed, so nothing is persisted: the caller's flush carries it, as it does everything else seeded here
    private function adoptLocaleless(string $name): ?EmailTemplate
    {
        $emailTemplate = $this->emailTemplateRepository->findOneBy(['name' => $name, 'locale' => '']);

        return $emailTemplate?->setLocale($this->defaultLocale);
    }

    /**
     * Gives a template already in place the data blocks its declaration has gained since - once each, and never again.
     *
     * A declaration goes on growing after the sites that use it were built: without this, a block added to an e-mail
     * would only ever reach the sites created after it, which is no way to ship a feature. Only data blocks (slots,
     * fields tables) are backfilled - they carry what the code renders and are identified by their name, whereas a
     * sentence is the admin's to write and has no identity to match on.
     *
     * "Once each" is what $seededBlocks is for: a block this template has already been offered is never offered
     * again, so one an admin took out on purpose stays out instead of coming back on every deployment. A template
     * seeded before that column existed records what it already holds on the first run, and only receives what it
     * genuinely never had.
     *
     * Appended at the end rather than at its declared position: the order of a composed template is the admin's,
     * and there is no telling where a new block belongs among blocks they have moved around.
     *
     * @param array<int, array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}> $blocks
     */
    private function backfillEmailTemplate(EmailTemplate $emailTemplate, array $blocks): int
    {
        $added = 0;
        $held = [];
        $position = -1;
        foreach ($emailTemplate->getBlocks() as $block) {
            $position = max($position, $block->getPosition() ?? 0);
            if ($block->isDataBlock() && null !== $block->getLabel()) {
                $held[] = $block->getLabel();
            }
        }

        $changed = false;
        foreach ($blocks as [$type, $heading, $level, $content, $label, $url]) {
            if (!in_array($type, EmailBlock::DATA_TYPES, true) || null === $label) {
                continue;
            }

            // Held right now, or offered once before and since removed: either way this template has had it
            if (in_array($label, $held, true) || $emailTemplate->hasBlockBeenSeeded($label)) {
                $emailTemplate->markBlockSeeded($label);
                $changed = true;

                continue;
            }

            $emailTemplate->addBlock(
                new EmailBlock()
                    ->setType($type)
                    ->setHeading($heading)
                    ->setLevel($level)
                    ->setContent($content)
                    ->setLabel($label)
                    ->setUrl($url)
                    ->setPosition(++$position)
            );
            $emailTemplate->markBlockSeeded($label);
            $changed = true;
            ++$added;
        }

        if ($changed) {
            $this->entityManager->persist($emailTemplate);
        }

        return $added;
    }

    // A Form seeded by an earlier version brought up to date in place - only ever on a still-restricted Form/field, so nothing an admin has taken over is touched
    private function backfillForm(Form $form, array $fields, ?string $action, ?array $actionConfig): void
    {
        if ($form->isRestricted()) {
            $this->backfillAction($form, $action, $actionConfig);
            $this->backfillLinks($form, $actionConfig);
        }

        $this->backfillFieldUrls($form, $fields);
    }

    // A renamed action takes its own config with it
    private function backfillAction(Form $form, ?string $action, ?array $actionConfig): void
    {
        if ($form->getAction() === $action) {
            return;
        }

        $form->setAction($action);

        // The rest of the config belongs to the action that just changed, so replacing it is the point - "links" doesn't: they are the Form's own, edited through their own collection editor, and follow the same never-overwrite rule as backfillLinks() rather than being reverted by a renamed action
        $links = $form->getActionConfig()['links'] ?? null;
        $form->setActionConfig(null === $links ? $actionConfig : array_merge($actionConfig ?? [], ['links' => $links]));
        $this->entityManager->persist($form);
    }

    // Backfilled on its own, same rule as a field's "url": a Form seeded before it declared any link gets them, one whose links an admin has already edited (emptied or otherwise) keeps that version
    private function backfillLinks(Form $form, ?array $actionConfig): void
    {
        if (!isset($actionConfig['links']) || array_key_exists('links', $form->getActionConfig() ?? [])) {
            return;
        }

        $form->setLinks($actionConfig['links']);
        $this->entityManager->persist($form);
    }

    // A restricted field seeded before its bundle declared an address gets it, one that already carries one keeps it
    private function backfillFieldUrls(Form $form, array $fields): void
    {
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
        $form = new Form()
            ->setName($name)
            ->setAction($action)
            ->setRestricted(true)
            ->setActionConfig($actionConfig);

        $position = 0;
        foreach ($fields as $fieldName => [$type, $label, $url]) {
            $form->addField(
                new FormField()
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
