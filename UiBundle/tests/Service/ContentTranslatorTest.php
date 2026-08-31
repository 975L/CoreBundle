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
use c975L\UiBundle\Repository\TranslationRepository;
use c975L\UiBundle\Service\ContentTranslator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ContentTranslatorTest extends TestCase
{
    private function createRequestStack(string $locale): RequestStack
    {
        $request = Request::create('/');
        $request->setLocale($locale);

        return new RequestStack([$request]);
    }

    /**
     * What the table holds, owner id => field => value.
     *
     * @param array<int, array<string, string|null>> $values
     */
    private function createRepository(array $values = []): TranslationRepository
    {
        $repository = $this->createStub(TranslationRepository::class);
        $repository->method('findValues')->willReturnCallback(
            static fn (string $ownerType, array $ownerIds, string $locale) => array_intersect_key($values, array_flip($ownerIds))
        );

        return $repository;
    }

    // The same, when the test counts the queries rather than reading what they give back
    private function createCountingRepository(array $values = []): TranslationRepository & MockObject
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->method('findValues')->willReturnCallback(
            static fn (string $ownerType, array $ownerIds, string $locale) => array_intersect_key($values, array_flip($ownerIds))
        );

        return $repository;
    }

    /**
     * @param list<string> $enabledLocales
     */
    private function createTranslator(TranslationRepository $repository, array $enabledLocales, string $locale = 'en'): ContentTranslator
    {
        return new ContentTranslator(
            $repository,
            $this->createStub(EntityManagerInterface::class),
            $this->createRequestStack($locale),
            new SiteLocales($enabledLocales, 'fr'),
        );
    }

    // The no-regression contract: a site that has not declared several languages never reads this table, and renders what it always rendered
    public function testASiteWithOneLocaleReadsNothingAndChangesNothing(): void
    {
        $repository = $this->createCountingRepository([7 => ['title' => 'Workshops']]);
        $repository->expects($this->never())->method('findValues');

        $translator = $this->createTranslator($repository, ['fr'], 'fr');

        $this->assertFalse($translator->isActive());
        $this->assertSame(['title' => 'Nos ateliers'], $translator->translate('ui_block', 7, ['title' => 'Nos ateliers'], ['title']));
    }

    // The language the content is written in is never translated: it is the one holding the text, and it plays the part of the msgid
    public function testTheDefaultLanguageIsNeverLookedUp(): void
    {
        $repository = $this->createCountingRepository([7 => ['title' => 'Workshops']]);
        $repository->expects($this->never())->method('findValues');

        $translator = $this->createTranslator($repository, ['fr', 'en'], 'fr');

        $this->assertSame(['title' => 'Nos ateliers'], $translator->translate('ui_block', 7, ['title' => 'Nos ateliers'], ['title']));
    }

    public function testATranslatedFieldIsLaidOverTheOneWrittenInTheBackOffice(): void
    {
        $translator = $this->createTranslator($this->createRepository([7 => ['title' => 'Workshops']]), ['fr', 'en']);

        $values = $translator->translate('ui_block', 7, ['title' => 'Nos ateliers', 'content' => 'Un par mois'], ['title', 'content']);

        $this->assertSame('Workshops', $values['title']);
        // A field nobody translated keeps its text, rather than leaving a hole in the page
        $this->assertSame('Un par mois', $values['content']);
    }

    // An entry opened then left blank does not make the page's title disappear
    public function testAnEmptyTranslationDoesNotWipeTheOriginal(): void
    {
        $translator = $this->createTranslator($this->createRepository([7 => ['title' => '', 'content' => null]]), ['fr', 'en']);

        $values = $translator->translate('ui_block', 7, ['title' => 'Nos ateliers', 'content' => 'Un par mois'], ['title', 'content']);

        $this->assertSame(['title' => 'Nos ateliers', 'content' => 'Un par mois'], $values);
    }

    // What is not declared translatable is not, even if the table holds a value for it: a css class name or an icon name has no business there
    public function testAFieldTheKindDoesNotDeclareIsLeftAlone(): void
    {
        $translator = $this->createTranslator($this->createRepository([7 => ['cssClasses' => 'text-danger']]), ['fr', 'en']);

        $values = $translator->translate('ui_block', 7, ['cssClasses' => 'text-muted'], ['title']);

        $this->assertSame(['cssClasses' => 'text-muted'], $values);
    }

    // A block never persisted has no id, so nothing to hang a translation on
    public function testABlockWithNoIdIsLeftAlone(): void
    {
        $repository = $this->createCountingRepository();
        $repository->expects($this->never())->method('findValues');

        $translator = $this->createTranslator($repository, ['fr', 'en']);

        $this->assertSame(['title' => 'Sans id'], $translator->translate('ui_block', null, ['title' => 'Sans id'], ['title']));
    }

    // One query per page and not per block: once the page is read, each block helps itself to what is already there
    public function testAWholePageCostsOneQuery(): void
    {
        $repository = $this->createCountingRepository([7 => ['title' => 'Workshops']]);
        $repository->expects($this->once())->method('findValues');

        $translator = $this->createTranslator($repository, ['fr', 'en']);
        $translator->preload('ui_block', [7, 8, 9]);

        $this->assertSame('Workshops', $translator->translate('ui_block', 7, ['title' => 'Nos ateliers'], ['title'])['title']);
        $this->assertSame('Rien', $translator->translate('ui_block', 8, ['title' => 'Rien'], ['title'])['title']);
        $this->assertSame('Rien non plus', $translator->translate('ui_block', 9, ['title' => 'Rien non plus'], ['title'])['title']);
    }

    // A language screen reads block by block: a container's slots help themselves to what its root has already read
    public function testALanguageScreenReadsTheWholeTreeInOneQuery(): void
    {
        $repository = $this->createCountingRepository([7 => ['title' => 'Workshops']]);
        $repository->expects($this->once())->method('findValues');

        $translator = $this->createTranslator($repository, ['fr', 'en']);
        $translator->preload('ui_block', [7, 8], 'en');

        $this->assertSame(['title' => 'Workshops'], $translator->values('ui_block', 7, 'en'));
        $this->assertSame([], $translator->values('ui_block', 8, 'en'));
    }

    // The languages a translation screen offers: every declared one, save the language the content is written in
    public function testTheLanguagesOfferedForWritingLeaveOutTheDefaultOne(): void
    {
        $translator = $this->createTranslator($this->createRepository(), ['fr', 'en', 'es']);

        $this->assertSame(['en', 'es'], $translator->getTranslatableLocales());
    }

    // A form cannot write these itself: its POST_SUBMIT fires before the root form is validated, so what it hands over waits for the flush that saves its owner (see TranslationWriteListener)
    public function testWhatAFormStagesIsHandedBackToWhoeverWritesIt(): void
    {
        $translator = $this->createTranslator($this->createRepository(), ['fr', 'es']);

        $translator->stage('ui_block', 7, 'es', ['title' => 'Hola']);

        $this->assertSame([['ui_block', 7, 'es', ['title' => 'Hola']]], $translator->takePending());
    }

    // Emptied on the way out, so a second flush in the same request writes nothing twice
    public function testWhatHasBeenTakenIsNotHandedBackAgain(): void
    {
        $translator = $this->createTranslator($this->createRepository(), ['fr', 'es']);

        $translator->stage('ui_block', 7, 'es', ['title' => 'Hola']);
        $translator->takePending();

        $this->assertSame([], $translator->takePending());
    }
}
