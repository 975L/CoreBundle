<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use Symfony\Component\Form\FormBuilderInterface;

// The "data" sub-form of the "section_cards" container kind - see AbstractSectionHeadContainerType
class SectionCardsType extends AbstractSectionHeadContainerType
{
    use HasBackgroundFieldTrait;

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        // A container lays out sections and is one itself: a row of cards is painted like any other, which is what lets a design stack colored flats without wrapping the row in a section it would otherwise need
        $this->addBackgroundField($builder);
    }
}
