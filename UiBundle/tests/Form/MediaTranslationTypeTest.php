<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Form\MediaTranslationType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

// The three texts of one media, on a real form rather than a builder stub: what the screen opens on, and what comes back from it
class MediaTranslationTypeTest extends TestCase
{
    // Only the fields the media says something in, each opening on the language's text or on the source between brackets
    public function testTheScreenOffersOnlyWhatTheMediaSays(): void
    {
        $form = $this->createForm(
            $this->createMedia('La boutique de démonstration', 'Un catalogue et ses filtres.', null),
            ['label' => 'La tienda de demostración', 'description' => '[Un catalogue et ses filtres.]', 'alt' => null],
        );

        $this->assertTrue($form->has('label'));
        $this->assertTrue($form->has('description'));
        $this->assertFalse($form->has('alt'), 'A media saying nothing in a field has no msgid to offer a language.');
        $this->assertSame('La tienda de demostración', $form->get('label')->getViewData());
        $this->assertSame('[Un catalogue et ses filtres.]', $form->get('description')->getViewData());
    }

    // The text being translated is put under the box it is translated into, as a landmark
    public function testEachFieldCarriesTheSourceAsItsHelp(): void
    {
        $form = $this->createForm($this->createMedia('La boutique de démonstration', null, null), ['label' => null]);

        $this->assertSame('La boutique de démonstration', $form->get('label')->createView()->vars['help']);
    }

    // What comes back is the three texts as an array, which BlockType hands to MediaTranslator::stage()
    public function testWhatComesBackIsWhatTheEditorWrote(): void
    {
        $form = $this->createForm(
            $this->createMedia('La boutique de démonstration', 'Un catalogue et ses filtres.', null),
            ['label' => '[La boutique de démonstration]', 'description' => '[Un catalogue et ses filtres.]'],
        );

        $form->submit(['label' => 'La tienda de demostración', 'description' => '[Un catalogue et ses filtres.]']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame(
            ['label' => 'La tienda de demostración', 'description' => '[Un catalogue et ses filtres.]'],
            $form->getData(),
        );
    }

    private function createMedia(?string $label, ?string $description, ?string $alt): Media
    {
        $media = new Media()->setLabel($label)->setDescription($description)->setAlt($alt);
        new \ReflectionProperty(Media::class, 'id')->setValue($media, 12);

        return $media;
    }

    // The type has no media to describe without the option, so its absence must name the option rather than complain about the null a default would have left
    public function testTheTypeRefusesToBeBuiltWithoutTheMediaItDescribes(): void
    {
        $this->expectException(MissingOptionsException::class);

        Forms::createFormFactory()->create(MediaTranslationType::class);
    }

    /** @param array<string, string|null> $values */
    private function createForm(Media $media, array $values): \Symfony\Component\Form\FormInterface
    {
        return Forms::createFormFactory()->create(MediaTranslationType::class, $values, ['media' => $media]);
    }
}
