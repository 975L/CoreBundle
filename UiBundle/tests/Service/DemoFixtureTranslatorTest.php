<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Service\DemoFixtureTranslator;
use c975L\UiBundle\Tests\Fixtures\DummyDemoFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class DemoFixtureTranslatorTest extends TestCase
{
    /** @var array<string, array<string, string>> locale => key => value */
    private const array CATALOG = [
        'fr' => ['title' => 'Table basse en chêne', 'description' => 'Une table en chêne massif.'],
        'en' => ['title' => 'Oak coffee table', 'description' => 'A solid oak table.'],
        'es' => ['title' => 'Mesa de centro de roble'],
    ];

    /** @param list<string> $locales */
    private function createTranslator(array $locales = ['fr', 'en', 'es']): DemoFixtureTranslator
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string => self::CATALOG[$locale ?? 'fr'][$id] ?? $id
        );

        return new DemoFixtureTranslator($translator, $locales, 'fr');
    }

    /** @return list<Translation> */
    private function translations(DemoFixtureTranslator $demoFixtureTranslator): array
    {
        return iterator_to_array($demoFixtureTranslator->translations(), false);
    }

    public function testEachLanguageTheCatalogSpeaksBecomesARow(): void
    {
        $demoFixtureTranslator = $this->createTranslator();
        $demoFixtureTranslator->stage(new DummyDemoFixture(12), 'shop_product', 'shop', ['title' => 'title', 'description' => 'description']);

        $rows = $this->translations($demoFixtureTranslator);

        $said = array_map(static fn (Translation $row): string => $row->getLocale() . '.' . $row->getField() . '=' . $row->getValue(), $rows);

        // Spanish says nothing of the description: the key comes back untranslated, and an untranslated row is what a reader already sees
        $this->assertSame([
            'en.title=Oak coffee table',
            'es.title=Mesa de centro de roble',
            'en.description=A solid oak table.',
        ], $said);

        $this->assertSame('shop_product', $rows[0]->getOwnerType());
        $this->assertSame(12, $rows[0]->getOwnerId());
    }

    public function testTheLanguageTheSiteIsWrittenInIsNeverWritten(): void
    {
        $demoFixtureTranslator = $this->createTranslator();
        $demoFixtureTranslator->stage(new DummyDemoFixture(12), 'shop_product', 'shop', ['title' => 'title']);

        foreach ($this->translations($demoFixtureTranslator) as $row) {
            $this->assertNotSame('fr', $row->getLocale());
        }
    }

    public function testASiteDeclaringOneLanguageStagesNothing(): void
    {
        $demoFixtureTranslator = $this->createTranslator(['fr']);
        $demoFixtureTranslator->stage(new DummyDemoFixture(12), 'shop_product', 'shop', ['title' => 'title']);

        $this->assertSame([], $this->translations($demoFixtureTranslator));
    }

    public function testAFixtureLeftUnflushedTakesItsTranslationsWithIt(): void
    {
        $demoFixtureTranslator = $this->createTranslator();
        $demoFixtureTranslator->stage(new DummyDemoFixture(null), 'shop_product', 'shop', ['title' => 'title']);

        $this->assertSame([], $this->translations($demoFixtureTranslator));
    }

    // A provider that wrapped its words before storing them stores its translations the same way, or the two read as two different texts to whatever compares them - a block holding its prose as "<div>...</div>" being the case this exists for
    public function testWhatTheProviderWrappedItsWordsInIsKept(): void
    {
        $demoFixtureTranslator = $this->createTranslator(['fr', 'en']);
        $demoFixtureTranslator->stage(
            new DummyDemoFixture(12),
            'ui_block',
            'shop',
            ['title' => 'title'],
            static fn (string $value): string => '<div>' . $value . '</div>',
        );

        $rows = $this->translations($demoFixtureTranslator);

        $this->assertCount(1, $rows);
        $this->assertSame('<div>Oak coffee table</div>', $rows[0]->getValue());
    }

    // The wrapper is put on after the comparison and never before it: a language saying the very same words as the writing one writes nothing, box or no box
    public function testTheWrapperIsNotWhatTellsTwoLanguagesApart(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Papa Câlin');

        $demoFixtureTranslator = new DemoFixtureTranslator($translator, ['fr', 'en'], 'fr');
        $demoFixtureTranslator->stage(
            new DummyDemoFixture(12),
            'ui_block',
            'book',
            ['title' => 'title'],
            static fn (string $value): string => '<div>' . $value . '</div>',
        );

        $this->assertSame([], $this->translations($demoFixtureTranslator));
    }

    public function testWhatWasReadIsNotReadAgain(): void
    {
        $demoFixtureTranslator = $this->createTranslator();
        $demoFixtureTranslator->stage(new DummyDemoFixture(12), 'shop_product', 'shop', ['title' => 'title']);

        $this->assertCount(2, $this->translations($demoFixtureTranslator));
        $this->assertSame([], $this->translations($demoFixtureTranslator));
    }
}
