<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use c975L\UiBundle\Form\ChoiceList\LinkTargetChoiceLoader;
use c975L\UiBundle\Registry\LinkTargetRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A link a block points at, picked the way a menu link is - the same searchable list of pages and sections - or typed by hand ("/shop", "https://…", "mailto:…"), which the list takes as a new entry of its own (see LinkTargetChoiceLoader)
class LinkTargetType extends AbstractType
{
    public function __construct(private readonly LinkTargetRegistry $registry)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'help' => 'label.link_target_help',
            'translation_domain' => 'ui',
            'choice_translation_domain' => false,
            // An empty first line even on a required field (a button's url): without it a new block would come out already pointing at the first page of the list
            'placeholder' => '',
            // One loader per field: it remembers the address this very field holds, so it can show it
            'choice_loader' => fn (Options $options): LinkTargetChoiceLoader => new LinkTargetChoiceLoader($this->registry->all()),
            // Read by EasyAdmin's autocomplete, which then lets a value typed into its search box become the field's own (see ChoiceAutocompleteExtension, which keeps the widget on it whatever the length of the list)
            'attr' => ['data-ea-autocomplete-allow-item-create' => 'true'],
        ]);
    }

    #[\Override]
    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
