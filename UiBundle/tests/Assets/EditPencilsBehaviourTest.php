<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Testing\JsCase;
use PHPUnit\Framework\Attributes\Group;

// assets/js/edit-pencils.js over the neutral marks a cached fragment carries: what blockEditOverlay reads is the url this controller writes, and a mark it misreads is a pencil opening another entity's form
#[Group('browser')]
class EditPencilsBehaviourTest extends JsCase
{
    private const string PATTERN = '/management/__kind__/__id__/edit';

    // A card names its kind and its id, and gets the form of that very entity
    public function testACardIsGivenTheFormOfItsOwnEntity(): void
    {
        $url = $this->pencils(
            '<article id="card" data-edit-entity="resistant:12"></article>',
            'return root.querySelector("#card").dataset.blockEditUrl;'
        );

        $this->assertSame('/management/resistant/12/edit', $url);
    }

    // A section of a fiche opens the fiche's form on its own field, whatever query the fiche's url already held
    public function testAFieldOpensTheFormOfTheEntityAroundItOnThatField(): void
    {
        $url = $this->pencils(
            '<div data-block-edit-url="/management/resistant/12/edit?referrer=x"><section id="story" data-edit-field="story"></section></div>',
            'return root.querySelector("#story").dataset.blockEditUrl;'
        );

        $this->assertSame('/management/resistant/12/edit?focusField=story', $url);
    }

    // A field with no entity around it has no form to open, and a broken mark is left without a pencil rather than given "undefined"
    public function testAMarkWithNothingToOpenIsLeftAlone(): void
    {
        $urls = $this->pencils(
            '<section id="orphan" data-edit-field="story"></section><article id="broken" data-edit-entity="resistant"></article>',
            'return [root.querySelector("#orphan").dataset.blockEditUrl ?? null, root.querySelector("#broken").dataset.blockEditUrl ?? null];'
        );

        $this->assertSame([null, null], $urls);
    }

    // A listing grows as the visitor scrolls: the cards it appends are marked too, the appended node itself included
    public function testACardAppendedLaterIsGivenItsFormToo(): void
    {
        $url = $this->pencils(
            '<div id="list"></div>',
            'const card = document.createElement("article");
             card.dataset.editEntity = "book:3";
             root.querySelector("#list").append(card);
             await new Promise((r) => setTimeout(r, 30));

             return card.dataset.blockEditUrl ?? null;'
        );

        $this->assertSame('/management/book/3/edit', $url, 'A card appended by the infinite scroll is left without its pencil.');
    }

    private function pencils(string $html, string $probe): mixed
    {
        return $this->observe(
            sprintf('<div hidden data-controller="edit-pencils" data-edit-pencils-pattern-value="%s"></div>%s', self::PATTERN, $html),
            ['edit-pencils' => 'edit-pencils'],
            $probe
        );
    }
}
