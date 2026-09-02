<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use c975L\UiBundle\Repository\FormRepository;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\EmailTemplateFactory;
use c975L\UiBundle\Service\FormSeeder;
use c975L\UiBundle\Service\FormTranslator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class FormSeederTest extends TestCase
{
    private const array CONTACT_FIELDS = [
        'en' => [
            'email' => ['email', 'Your e-mail', null],
            'message' => ['textarea', 'Your message', null],
        ],
        'fr' => [
            'email' => ['email', 'Votre e-mail', null],
            'message' => ['textarea', 'Votre message', null],
        ],
    ];

    private array $persisted = [];

    protected function setUp(): void
    {
        $this->persisted = [];
    }

    public function testEnsureFormSeedsARestrictedFormWithItsFields(): void
    {
        $seeder = $this->seeder('en');

        $seeder->ensureForm('contact', self::CONTACT_FIELDS, 'send_email', ['to' => 'contact@example.test']);

        $form = $this->onlyPersisted(Form::class);
        $this->assertSame('contact', $form->getName());
        $this->assertSame('send_email', $form->getAction());
        $this->assertSame(['to' => 'contact@example.test'], $form->getActionConfig());
        $this->assertTrue($form->isRestricted());

        $fields = $form->getFields()->toArray();
        $this->assertSame(['email', 'message'], array_map(static fn (FormField $f): string => $f->getName(), $fields));
        $this->assertSame(['Your e-mail', 'Your message'], array_map(static fn (FormField $f): string => $f->getLabel(), $fields));
        $this->assertSame([0, 1], array_map(static fn (FormField $f): int => $f->getPosition(), $fields));
    }

    // Form::$name is unique site-wide, so there is one "contact" Form, seeded in kernel.default_locale
    public function testEnsureFormSeedsTheLabelsOfTheDefaultLocale(): void
    {
        $seeder = $this->seeder('fr');

        $seeder->ensureForm('contact', self::CONTACT_FIELDS);

        $labels = array_map(static fn (FormField $f): string => $f->getLabel(), $this->onlyPersisted(Form::class)->getFields()->toArray());
        $this->assertSame(['Votre e-mail', 'Votre message'], $labels);
    }

    // A locale the caller declares no fields for falls back on "en" rather than failing
    public function testEnsureFormFallsBackOnEnglishForAnUndeclaredLocale(): void
    {
        $seeder = $this->seeder('es');

        $seeder->ensureForm('contact', self::CONTACT_FIELDS);

        $labels = array_map(static fn (FormField $f): string => $f->getLabel(), $this->onlyPersisted(Form::class)->getFields()->toArray());
        $this->assertSame(['Your e-mail', 'Your message'], $labels);
    }

    // Idempotent: running the seed again on an already-seeded, up-to-date Form creates nothing
    public function testEnsureFormDoesNotSeedTwice(): void
    {
        $existing = new Form()->setName('contact')->setAction('send_email')->setRestricted(true);
        $seeder = $this->seeder('en', $existing);

        $seeder->ensureForm('contact', self::CONTACT_FIELDS, 'send_email');

        $this->assertSame([], $this->persisted);
    }

    // A Form seeded before its owning bundle gained an action is brought up to date in place
    public function testEnsureFormBackfillsAMissingActionOnARestrictedForm(): void
    {
        $existing = new Form()->setName('contact')->setRestricted(true);
        $seeder = $this->seeder('en', $existing);

        $seeder->ensureForm('contact', self::CONTACT_FIELDS, 'send_email', ['to' => 'contact@example.test']);

        $this->assertSame('send_email', $existing->getAction());
        $this->assertSame(['to' => 'contact@example.test'], $existing->getActionConfig());
        $this->assertSame([$existing], $this->persisted);
    }

    // A Form an admin has taken over is never rewritten
    public function testEnsureFormLeavesAnUnrestrictedFormAlone(): void
    {
        $existing = new Form()->setName('contact')->setRestricted(false);
        $seeder = $this->seeder('en', $existing);

        $seeder->ensureForm('contact', self::CONTACT_FIELDS, 'send_email');

        $this->assertNull($existing->getAction());
        $this->assertSame([], $this->persisted);
    }

    // A field gaining a "url" in a later version gets it, as long as it is still the seeded null
    public function testEnsureFormBackfillsAStillNullFieldUrl(): void
    {
        $field = new FormField()->setName('gdpr')->setType('checkbox')->setRestricted(true);
        $existing = new Form()->setName('contact')->setAction('send_email')->setRestricted(true)->addField($field);
        $seeder = $this->seeder('en', $existing);

        $seeder->ensureForm('contact', ['en' => ['gdpr' => ['checkbox', 'I accept', '/terms']]], 'send_email');

        $this->assertSame('/terms', $field->getUrl());
    }

    // ... but an admin's own edit, blank included, is never overwritten
    public function testEnsureFormLeavesAnAlreadySetFieldUrlAlone(): void
    {
        $field = new FormField()->setName('gdpr')->setType('checkbox')->setRestricted(true)->setUrl('');
        $existing = new Form()->setName('contact')->setAction('send_email')->setRestricted(true)->addField($field);
        $seeder = $this->seeder('en', $existing);

        $seeder->ensureForm('contact', ['en' => ['gdpr' => ['checkbox', 'I accept', '/terms']]], 'send_email');

        $this->assertSame('', $field->getUrl());
    }

    // The links shown under the submit button are seeded in the same locale as the labels, and land in the action config
    public function testEnsureFormSeedsTheLinksOfTheDefaultLocale(): void
    {
        $seeder = $this->seeder('fr');

        $seeder->ensureForm('register', self::CONTACT_FIELDS, 'register', null, [
            'en' => [['label' => 'Sign in', 'url' => '/login']],
            'fr' => [['label' => 'Me connecter', 'url' => '/login']],
        ]);

        $this->assertSame([['label' => 'Me connecter', 'url' => '/login']], $this->onlyPersisted(Form::class)->getLinks());
    }

    // A Form seeded before its bundle declared any link gets them, without the action having to change
    public function testEnsureFormBackfillsMissingLinks(): void
    {
        $existing = new Form()->setName('register')->setAction('register')->setRestricted(true);
        $seeder = $this->seeder('en', $existing);

        $seeder->ensureForm('register', self::CONTACT_FIELDS, 'register', null, ['en' => [['label' => 'Sign in', 'url' => '/login']]]);

        $this->assertSame([['label' => 'Sign in', 'url' => '/login']], $existing->getLinks());
        $this->assertSame([$existing], $this->persisted);
    }

    // ... but an admin who has edited them, emptied included, keeps their own version - built through setLinks(), the way the back-office really empties them, and not by hand-writing the key
    public function testEnsureFormLeavesAlreadyEditedLinksAlone(): void
    {
        $existing = new Form()->setName('register')->setAction('register')->setRestricted(true)
            ->setLinks([['label' => 'Mine', 'url' => '/mine']]);
        $existing->setLinks([]);
        $seeder = $this->seeder('en', $existing);

        $seeder->ensureForm('register', self::CONTACT_FIELDS, 'register', null, ['en' => [['label' => 'Sign in', 'url' => '/login']]]);

        $this->assertSame([], $existing->getLinks());
        $this->assertSame([], $this->persisted);
    }

    // Renaming a seeded action used to replace the whole column, taking the admin's own links down with it - the rest of the config does belong to the action, the links never did
    public function testEnsureFormKeepsEditedLinksWhenTheActionIsRenamed(): void
    {
        $existing = new Form()->setName('register')->setAction('register')->setRestricted(true)
            ->setActionConfig(['links' => [['label' => 'My own', 'url' => '/mine']], 'stale' => 'value']);
        $seeder = $this->seeder('en', $existing);

        $seeder->ensureForm('register', self::CONTACT_FIELDS, 'register_v2', ['fresh' => 'value'], ['en' => [['label' => 'Sign in', 'url' => '/login']]]);

        $this->assertSame('register_v2', $existing->getAction());
        $this->assertSame([['label' => 'My own', 'url' => '/mine']], $existing->getLinks());
        $this->assertSame(['fresh' => 'value', 'links' => [['label' => 'My own', 'url' => '/mine']]], $existing->getActionConfig());
    }

    // A Form that never carried any link still receives the seeded ones through the same rename
    public function testEnsureFormSeedsLinksWhenTheActionIsRenamedAndNoneWereSet(): void
    {
        $existing = new Form()->setName('register')->setAction('register')->setRestricted(true);
        $seeder = $this->seeder('en', $existing);

        $seeder->ensureForm('register', self::CONTACT_FIELDS, 'register_v2', null, ['en' => [['label' => 'Sign in', 'url' => '/login']]]);

        $this->assertSame([['label' => 'Sign in', 'url' => '/login']], $existing->getLinks());
    }

    // Same locale resolution as the fields above, final fallback included: a bundle shipping its blocks in "fr" only used to fatal on an undefined "en" key rather than seed an empty template the admin can then fill
    public function testEnsureEmailTemplateSurvivesALocaleItShipsNoBlocksFor(): void
    {
        $seeder = $this->seeder('es');

        $seeder->ensureEmailTemplate('account_validation', [
            'fr' => [['heading', 'Bienvenue', 'h1', null, null, null]],
        ]);

        $emailTemplate = $this->onlyPersisted(EmailTemplate::class);
        $this->assertSame('account_validation', $emailTemplate->getName());
        $this->assertCount(0, $emailTemplate->getBlocks());
    }

    public function testEnsureEmailTemplateSeedsARestrictedTemplateWithItsBlocks(): void
    {
        $seeder = $this->seeder('en');

        $seeder->ensureEmailTemplate('account_validation', [
            'en' => [
                ['heading', 'Welcome', 'h1', null, null, null],
                ['button', null, null, null, 'Confirm', 'https://example.test/confirm'],
            ],
        ]);

        $emailTemplate = $this->onlyPersisted(EmailTemplate::class);
        $this->assertSame('account_validation', $emailTemplate->getName());
        $this->assertTrue($emailTemplate->isRestricted());

        $blocks = $emailTemplate->getBlocks()->toArray();
        $this->assertCount(2, $blocks);
        $this->assertSame('Welcome', $blocks[0]->getHeading());
        $this->assertSame('https://example.test/confirm', $blocks[1]->getUrl());
        $this->assertSame([0, 1], array_map(static fn ($block): int => $block->getPosition(), $blocks));
    }

    // Idempotent the same way: an existing template's wording stays the admin's, a sentence having no identity to match on and never being backfilled - only data blocks are, below
    public function testEnsureEmailTemplateDoesNotSeedTwice(): void
    {
        $seeder = $this->seeder('en', null, new EmailTemplate());

        $seeder->ensureEmailTemplate('account_validation', ['en' => [['heading', 'Welcome', 'h1', null, null, null]]]);

        $this->assertSame([], $this->persisted);
    }

    // A declaration goes on growing after the sites using it were built: without this, a slot added to an e-mail would only ever reach the sites created after it
    public function testEnsureEmailTemplateGivesAnExistingTemplateADataBlockItNeverHad(): void
    {
        $existing = new EmailTemplate()->setName('confirm_order')->setLocale('en')
            ->addBlock(new EmailBlock()->setType(EmailBlock::TYPE_TEXT)->setContent('Thanks')->setPosition(0))
            ->addBlock(new EmailBlock()->setType(EmailBlock::TYPE_SLOT)->setLabel('items')->setPosition(1));
        $seeder = $this->seeder('en', null, $existing);

        $seeder->ensureEmailTemplate('confirm_order', ['en' => [
            ['text', null, null, 'Thanks', null, null],
            ['slot', null, null, null, 'items', null],
            ['slot', null, null, null, 'account_invitation', null],
        ]]);

        $blocks = $existing->getBlocks()->toArray();
        $this->assertCount(3, $blocks);
        // Appended rather than put at its declared position: the order of a composed template is the admin's
        $this->assertSame('account_invitation', $blocks[2]->getLabel());
        $this->assertSame(2, $blocks[2]->getPosition());
        $this->assertSame([$existing], $this->persisted);
    }

    // The second deployment must not hand it a second copy
    public function testEnsureEmailTemplateBackfillsADataBlockOnlyOnce(): void
    {
        $existing = new EmailTemplate()->setName('confirm_order')->setLocale('en');
        $seeder = $this->seeder('en', null, $existing);

        $declaration = ['en' => [['slot', null, null, null, 'account_invitation', null]]];
        $seeder->ensureEmailTemplate('confirm_order', $declaration);
        $seeder->ensureEmailTemplate('confirm_order', $declaration);

        $this->assertCount(1, $existing->getBlocks());
        $this->assertSame(['account_invitation'], $existing->getSeededBlocks());
    }

    // The whole point of remembering what was offered: put back on every deployment what somebody removed on purpose is worse than never offering it
    public function testEnsureEmailTemplateNeverPutsBackADataBlockAnAdminRemoved(): void
    {
        $existing = new EmailTemplate()->setName('confirm_order')->setLocale('en')->setSeededBlocks(['account_invitation']);
        $seeder = $this->seeder('en', null, $existing);

        $seeder->ensureEmailTemplate('confirm_order', ['en' => [['slot', null, null, null, 'account_invitation', null]]]);

        $this->assertCount(0, $existing->getBlocks());
    }

    // Seeded before that column existed: what it already holds is recorded on the first run, so removing it afterwards sticks
    public function testEnsureEmailTemplateRecordsTheDataBlocksATemplateAlreadyHolds(): void
    {
        $existing = new EmailTemplate()->setName('confirm_order')->setLocale('en')
            ->addBlock(new EmailBlock()->setType(EmailBlock::TYPE_SLOT)->setLabel('items')->setPosition(0));
        $seeder = $this->seeder('en', null, $existing);

        $seeder->ensureEmailTemplate('confirm_order', ['en' => [['slot', null, null, null, 'items', null]]]);

        $this->assertCount(1, $existing->getBlocks());
        $this->assertSame(['items'], $existing->getSeededBlocks());
    }

    // A sentence is the admin's to write and has no identity to match on: backfilling one would put back wording they deleted, or duplicate wording they rewrote
    public function testEnsureEmailTemplateNeverBackfillsWording(): void
    {
        $existing = new EmailTemplate()->setName('confirm_order')->setLocale('en');
        $seeder = $this->seeder('en', null, $existing);

        $seeder->ensureEmailTemplate('confirm_order', ['en' => [
            ['text', null, null, 'A sentence this template does not carry', null, null],
            ['heading', 'A heading either', 'h1', null, null, null],
        ]]);

        $this->assertCount(0, $existing->getBlocks());
        $this->assertSame([], $this->persisted);
    }

    // Nothing is ever flushed here - a batch of seeds stays the caller's own single transaction
    public function testSeedingNeverFlushes(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $seeder = new FormSeeder(
            $entityManager,
            $this->formRepository(null),
            $this->emailTemplateRepository(null),
            new EmailTemplateFactory(),
            new FormTranslator(),
            'en'
        );

        $seeder->ensureForm('contact', self::CONTACT_FIELDS);
        $seeder->ensureEmailTemplate('account_validation', ['en' => [['heading', 'Welcome', 'h1', null, null, null]]]);
    }

    // One e-mail, one row per language it is written in: the person editing the German version edits German text, never a locale column on a French one
    public function testEnsureEmailTemplateSeedsOneRowPerLanguageItIsWrittenIn(): void
    {
        $seeder = $this->seeder('fr', enabledLocales: ['fr', 'en']);

        $seeder->ensureEmailTemplate('account_validation', [
            'fr' => [['heading', 'Bienvenue', 'h1', null, null, null]],
            'en' => [['heading', 'Welcome', 'h1', null, null, null]],
        ]);

        $seeded = array_values(array_filter($this->persisted, static fn (object $e): bool => $e instanceof EmailTemplate));
        $this->assertCount(2, $seeded);
        $this->assertSame(['fr', 'en'], array_map(static fn (EmailTemplate $t): ?string => $t->getLocale(), $seeded));
    }

    // A language the caller wrote nothing in gets nothing rather than somebody else's words - the site's own language being the exception, covered above
    public function testEnsureEmailTemplateSkipsAnExtraLanguageItShipsNoBlocksFor(): void
    {
        $seeder = $this->seeder('fr', enabledLocales: ['fr', 'de']);

        $seeder->ensureEmailTemplate('account_validation', [
            'fr' => [['heading', 'Bienvenue', 'h1', null, null, null]],
        ]);

        $emailTemplate = $this->onlyPersisted(EmailTemplate::class);
        $this->assertSame('fr', $emailTemplate->getLocale());
    }

    // A row written before e-mails had a language is the site's own row, given that language instead of being left beside a duplicate of itself - c975l:ui:email-templates:ensure does the same for every row at once, and neither has to run before the other
    public function testEnsureEmailTemplateAdoptsARowWrittenBeforeEmailsHadALanguage(): void
    {
        $legacy = new EmailTemplate()->setName('account_validation')->setLocale('');
        $seeder = $this->seeder('fr', localelessEmailTemplate: $legacy);

        $seeder->ensureEmailTemplate('account_validation', ['fr' => [['heading', 'Bienvenue', 'h1', null, null, null]]]);

        $this->assertSame('fr', $legacy->getLocale());
        $this->assertSame([], array_filter($this->persisted, static fn (object $e): bool => $e instanceof EmailTemplate));
    }

    // Only the site's own language adopts it: the row it belonged to was the one e-mail there was, and giving it to a language the site merely answers in would take it from the site's own
    public function testEnsureEmailTemplateNeverAdoptsARowForAnExtraLanguage(): void
    {
        $legacy = new EmailTemplate()->setName('account_validation')->setLocale('');
        $seeder = $this->seeder('fr', localelessEmailTemplate: $legacy, enabledLocales: ['fr', 'en']);

        $seeder->ensureEmailTemplate('account_validation', [
            'fr' => [['heading', 'Bienvenue', 'h1', null, null, null]],
            'en' => [['heading', 'Welcome', 'h1', null, null, null]],
        ]);

        $this->assertSame('fr', $legacy->getLocale());
        $emailTemplate = $this->onlyPersisted(EmailTemplate::class);
        $this->assertSame('en', $emailTemplate->getLocale());
    }

    // The words for the site's other languages ship with the declaration, so a bilingual site's contact form is readable in both from the moment it is seeded rather than waiting for an admin to retype them
    public function testASeededFormCarriesItsOtherLanguagesWording(): void
    {
        $translations = [];
        $seeder = $this->bilingualSeeder($translations);

        $seeder->ensureForm('contact', self::CONTACT_FIELDS);

        // The fields have no id until the caller's flush, which is exactly what the queue is for
        $this->assertSame([], $translations);

        foreach ($this->onlyPersisted(Form::class)->getFields() as $position => $field) {
            new \ReflectionProperty(FormField::class, 'id')->setValue($field, 100 + $position);
        }

        $seeder->writeQueuedTranslations();

        $this->assertSame([
            [Translation::OWNER_FORM_FIELD, 100, 'en', ['label' => 'Your e-mail']],
            [Translation::OWNER_FORM_FIELD, 101, 'en', ['label' => 'Your message']],
        ], $translations);
    }

    // A form already in place has been through the back-office since, and what it says in each language is an admin's to write - the same rule every backfill above follows
    public function testAFormAlreadyInPlaceIsNeverRetranslated(): void
    {
        $translations = [];
        $seeder = $this->bilingualSeeder($translations, new Form()->setName('contact')->setRestricted(true));

        $seeder->ensureForm('contact', self::CONTACT_FIELDS);
        $seeder->writeQueuedTranslations();

        $this->assertSame([], $translations);
    }

    // Nothing is written twice: a second flush finds the queue empty
    public function testTheQueueIsEmptiedOnItsWayOut(): void
    {
        $translations = [];
        $seeder = $this->bilingualSeeder($translations);

        $seeder->ensureForm('contact', self::CONTACT_FIELDS);
        foreach ($this->onlyPersisted(Form::class)->getFields() as $position => $field) {
            new \ReflectionProperty(FormField::class, 'id')->setValue($field, 100 + $position);
        }

        $seeder->writeQueuedTranslations();
        $written = \count($translations);
        $seeder->writeQueuedTranslations();

        $this->assertSame($written, \count($translations));
    }

    // A seeder on a site written in French and also read in English, recording what it stores rather than writing it
    private function bilingualSeeder(array &$translations, ?Form $existingForm = null): FormSeeder
    {
        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('isActive')->willReturn(true);
        $contentTranslator->method('getTranslatableLocales')->willReturn(['en']);
        $contentTranslator->method('store')->willReturnCallback(
            static function (string $ownerType, int $ownerId, string $locale, array $values) use (&$translations): void {
                $translations[] = [$ownerType, $ownerId, $locale, $values];
            }
        );

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        return new FormSeeder(
            $entityManager,
            $this->formRepository($existingForm),
            $this->emailTemplateRepository(null),
            new EmailTemplateFactory(),
            new FormTranslator($contentTranslator),
            'fr',
            ['fr', 'en']
        );
    }

    private function seeder(string $defaultLocale, ?Form $existingForm = null, ?EmailTemplate $existingEmailTemplate = null, array $enabledLocales = [], ?EmailTemplate $localelessEmailTemplate = null): FormSeeder
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        return new FormSeeder(
            $entityManager,
            $this->formRepository($existingForm),
            $this->emailTemplateRepository($existingEmailTemplate, $localelessEmailTemplate),
            new EmailTemplateFactory(),
            new FormTranslator(),
            $defaultLocale,
            $enabledLocales
        );
    }

    private function formRepository(?Form $existing): FormRepository
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        return $repository;
    }

    // Answers on the pair it is asked about, the seeder looking a row up by name and locale and, for the site's own language only, by the empty locale rows written before that column existed
    private function emailTemplateRepository(?EmailTemplate $existing, ?EmailTemplate $localeless = null): EmailTemplateRepository
    {
        $repository = $this->createStub(EmailTemplateRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?EmailTemplate => '' === ($criteria['locale'] ?? null) ? $localeless : $existing);

        return $repository;
    }

    // The single entity of that class handed to persist()
    private function onlyPersisted(string $class): object
    {
        $matching = array_values(array_filter($this->persisted, static fn (object $entity): bool => $entity instanceof $class));
        $this->assertCount(1, $matching, sprintf('Expected exactly one persisted %s.', $class));

        return $matching[0];
    }
}
