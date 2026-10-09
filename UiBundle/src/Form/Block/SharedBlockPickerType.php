<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Repository\SharedBlockRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Picks which shared block to show, by slug; its content is written on its own screen (see SharedBlockCrudController)
class SharedBlockPickerType extends AbstractType
{
    public function __construct(private readonly SharedBlockRepository $sharedBlockRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($this->sharedBlockRepository->findBy([], ['name' => 'ASC']) as $sharedBlock) {
            $choices[(string) $sharedBlock->getName()] = (string) $sharedBlock->getSlug();
        }

        $builder->add('slug', ChoiceType::class, [
            'label' => 'label.shared_block',
            'choices' => $choices,
            'placeholder' => 'label.choose_shared_block',
            'help' => 'label.shared_block_picker_help',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'label' => false,
            'translation_domain' => 'ui',
        ]);
    }
}
