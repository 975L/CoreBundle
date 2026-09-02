<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Repository\TranslationRepository;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\FormTranslator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

// What an admin typed in the form builder is text a visitor reads, so it says something else in each of the site's languages - and nothing at all changes on a site declaring one
class FormTranslatorTest extends TestCase
{
    private const int FIELD_ID = 7;

    private const int OUTPUT_ID = 9;

    // A form the site was written in French, read by an English visitor
    public function testAFieldReadsWhatItsLanguageSays(): void
    {
        $field = $this->createField('Nom', 'Votre nom');
        $translator = $this->createTranslator(['fr', 'en'], [
            Translation::OWNER_FORM_FIELD => [self::FIELD_ID => ['label' => 'Name', 'placeholder' => 'Your name']],
        ]);

        $this->assertSame('Name', $translator->getLabel($field));
        $this->assertSame('Your name', $translator->getPlaceholder($field));
    }

    // A half-translated form reads in two languages rather than showing holes - the same merge a page's own texts go through
    public function testATextNobodyTranslatedKeepsTheWordsItWasWrittenIn(): void
    {
        $field = $this->createField('Nom', 'Votre nom');
        $translator = $this->createTranslator(['fr', 'en'], [
            Translation::OWNER_FORM_FIELD => [self::FIELD_ID => ['label' => 'Name']],
        ]);

        $this->assertSame('Name', $translator->getLabel($field));
        $this->assertSame('Votre nom', $translator->getPlaceholder($field));
    }

    // The short-circuit the whole design rests on: one language, nothing read, nothing to read
    public function testASiteDeclaringOneLanguageReadsTheTextAsItWasWritten(): void
    {
        $field = $this->createField('Nom', 'Votre nom');
        $translator = $this->createTranslator(['fr'], [
            Translation::OWNER_FORM_FIELD => [self::FIELD_ID => ['label' => 'Name']],
        ]);

        $this->assertFalse($translator->isActive());
        $this->assertSame('Nom', $translator->getLabel($field));
    }

    // A calculator's results are words too: the one beside the number, and the unit after it
    public function testAResultReadsItsLabelAndItsUnitInTheLanguageBeingRead(): void
    {
        $output = $this->createOutput('Économies', 'litres');
        $translator = $this->createTranslator(['fr', 'en'], [
            Translation::OWNER_FORM_OUTPUT => [self::OUTPUT_ID => ['label' => 'Savings', 'unit' => 'litres']],
        ]);

        $this->assertSame('Savings', $translator->getLabel($output));
        $this->assertSame('litres', $translator->getUnit($output));
    }

    // Fields and results are named apart, so a field and a result carrying the same id never read each other's words
    public function testAFieldAndAResultSharingAnIdDoNotReadEachOther(): void
    {
        $field = $this->createField('Nom', null, self::FIELD_ID);
        $output = $this->createOutput('Économies', null, self::FIELD_ID);
        $translator = $this->createTranslator(['fr', 'en'], [
            Translation::OWNER_FORM_FIELD => [self::FIELD_ID => ['label' => 'Name']],
        ]);

        $this->assertSame('Name', $translator->getLabel($field));
        $this->assertSame('Économies', $translator->getLabel($output));
    }

    // What a language screen offers in a field nobody has written yet: the source between brackets, both the thing to translate and the mark of what is left to do
    public function testALanguageScreenOffersTheSourceBetweenBracketsWhereNothingIsWritten(): void
    {
        $field = $this->createField('Nom', 'Votre nom');
        $translator = $this->createTranslator(['fr', 'en'], [
            Translation::OWNER_FORM_FIELD => [self::FIELD_ID => ['label' => 'Name']],
        ], byOwner: [self::FIELD_ID => ['en' => ['label' => 'Name']]]);

        $this->assertSame(
            ['label' => 'Name', 'placeholder' => '[Votre nom]', 'defaultValue' => null],
            $translator->promptValues($field, 'en')
        );
    }

    // Written without an id - a row a seeder has just built, not yet flushed - there is nothing to hang a translation on, and asking for one is not an error
    public function testARowWithoutAnIdStagesNothing(): void
    {
        $field = $this->createField('Nom', null, null);
        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->expects($this->never())->method('stage');

        new FormTranslator($contentTranslator)->stage($field, 'en', ['label' => 'Name']);
    }

    private function createField(string $label, ?string $placeholder = null, ?int $id = self::FIELD_ID): FormField
    {
        $field = new FormField()
            ->setName('name')
            ->setLabel($label)
            ->setPlaceholder($placeholder);

        new \ReflectionProperty(FormField::class, 'id')->setValue($field, $id);

        return $field;
    }

    private function createOutput(string $label, ?string $unit = null, ?int $id = self::OUTPUT_ID): FormOutput
    {
        $output = new FormOutput()
            ->setName('savings')
            ->setLabel($label)
            ->setExpression('1')
            ->setUnit($unit);

        new \ReflectionProperty(FormOutput::class, 'id')->setValue($output, $id);

        return $output;
    }

    /**
     * @param list<string>                                          $enabledLocales
     * @param array<string, array<int, array<string, string|null>>> $values         owner type => owner id => field => value
     * @param array<int, array<string, array<string, string|null>>> $byOwner        owner id => locale => field => value, for the language screen
     */
    private function createTranslator(array $enabledLocales, array $values, array $byOwner = []): FormTranslator
    {
        $repository = $this->createStub(TranslationRepository::class);
        $repository->method('findValues')->willReturnCallback(
            static fn (string $ownerType, array $ownerIds, string $locale): array => array_intersect_key($values[$ownerType] ?? [], array_flip($ownerIds))
        );
        $repository->method('findByOwner')->willReturnCallback(
            static fn (string $ownerType, int $ownerId): array => $byOwner[$ownerId] ?? []
        );

        $request = Request::create('/');
        $request->setLocale('en');

        return new FormTranslator(new ContentTranslator(
            $repository,
            $this->createStub(EntityManagerInterface::class),
            new RequestStack([$request]),
            new SiteLocales($enabledLocales, 'fr'),
        ));
    }
}
