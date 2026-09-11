<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\ContentTranslator;
use PHPUnit\Framework\TestCase;

// A block holds whole collections as json - a FAQ's questions, a grid's cards - and those are prose a visitor reads like any other. A translation names one field of one entry by its place.
class ContentTranslatorCollectionsTest extends TestCase
{
    private const array DATA = [
        'title' => 'Fonctionnalités',
        'cards' => [
            ['icon' => 'star', 'title' => 'Catalogue produits', 'text' => 'Produits organisés par catégories.'],
            ['icon' => 'link', 'title' => 'Recommandations', 'text' => 'Des suggestions calculées.'],
        ],
    ];

    // Read off the data rather than declared: one name per entry the collection actually holds
    public function testEveryEntryOfACollectionIsNamed(): void
    {
        $this->assertSame(
            ['title', 'cards.0.title', 'cards.0.text', 'cards.1.title', 'cards.1.text'],
            ContentTranslator::expand(self::DATA, ['title'], ['cards' => ['title', 'text']]),
        );
    }

    // A kind declaring no repeated text is named exactly as it always was
    public function testAKindWithoutCollectionsIsNamedAsBefore(): void
    {
        $this->assertSame(['title'], ContentTranslator::expand(self::DATA, ['title'], []));
    }

    // A collection the data does not hold at all - a kind just switched to, a block saved before the field existed
    public function testACollectionTheDataDoesNotHoldNamesNothing(): void
    {
        $this->assertSame(['title'], ContentTranslator::expand(['title' => 'x'], ['title'], ['cards' => ['title']]));
    }

    // The icon of a card is not prose: it says the same thing in every language, and is never named
    public function testOnlyTheDeclaredFieldsOfAnEntryAreNamed(): void
    {
        $named = ContentTranslator::expand(self::DATA, [], ['cards' => ['title']]);

        $this->assertSame(['cards.0.title', 'cards.1.title'], $named);
    }

    // Every name expand() gives reads back the value it stands for, a plain key as well as one entry of a collection
    public function testANameReadsBackTheValueItStandsFor(): void
    {
        $this->assertSame('Fonctionnalités', ContentTranslator::read(self::DATA, 'title'));
        $this->assertSame('Des suggestions calculées.', ContentTranslator::read(self::DATA, 'cards.1.text'));
    }

    // An entry the data no longer holds reads as nothing rather than as a notice - a card deleted since its name was given
    public function testANameTheDataNoLongerHoldsReadsNothing(): void
    {
        $this->assertNull(ContentTranslator::read(self::DATA, 'cards.2.title'));
        $this->assertNull(ContentTranslator::read(self::DATA, 'title.0.text'));
    }
}
