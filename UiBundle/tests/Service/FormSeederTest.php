<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use c975L\UiBundle\Repository\FormRepository;
use c975L\UiBundle\Service\FormSeeder;
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

    // Idempotent the same way, and with no backfill: an existing template's blocks stay the admin's
    public function testEnsureEmailTemplateDoesNotSeedTwice(): void
    {
        $seeder = $this->seeder('en', null, new EmailTemplate());

        $seeder->ensureEmailTemplate('account_validation', ['en' => [['heading', 'Welcome', 'h1', null, null, null]]]);

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
            'en'
        );

        $seeder->ensureForm('contact', self::CONTACT_FIELDS);
        $seeder->ensureEmailTemplate('account_validation', ['en' => [['heading', 'Welcome', 'h1', null, null, null]]]);
    }

    private function seeder(string $defaultLocale, ?Form $existingForm = null, ?EmailTemplate $existingEmailTemplate = null): FormSeeder
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        return new FormSeeder(
            $entityManager,
            $this->formRepository($existingForm),
            $this->emailTemplateRepository($existingEmailTemplate),
            $defaultLocale
        );
    }

    private function formRepository(?Form $existing): FormRepository
    {
        $repository = $this->createStub(FormRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        return $repository;
    }

    private function emailTemplateRepository(?EmailTemplate $existing): EmailTemplateRepository
    {
        $repository = $this->createStub(EmailTemplateRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

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
