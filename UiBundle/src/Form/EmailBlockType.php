<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Form\Util\CollectionReconciler;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Flat shape: every kind shares one set of columns, each meaningful only for the kinds named in its help text
class EmailBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'label.email_block_type',
                'choices' => [
                    'label.email_block_type_heading' => EmailBlock::TYPE_HEADING,
                    'label.email_block_type_text' => EmailBlock::TYPE_TEXT,
                    'label.email_block_type_button' => EmailBlock::TYPE_BUTTON,
                    'label.email_block_type_image' => EmailBlock::TYPE_IMAGE,
                    'label.email_block_type_divider' => EmailBlock::TYPE_DIVIDER,
                    'label.email_block_type_spacer' => EmailBlock::TYPE_SPACER,
                    'label.email_block_type_fields_table' => EmailBlock::TYPE_FIELDS_TABLE,
                    'label.email_block_type_slot' => EmailBlock::TYPE_SLOT,
                ],
            ])
            ->add('heading', TextType::class, [
                'label' => 'label.email_block_heading',
                'help' => 'label.email_block_heading_help',
                'required' => false,
            ])
            ->add('level', ChoiceType::class, [
                'label' => 'label.email_block_level',
                'required' => false,
                'placeholder' => false,
                'choices' => [
                    'H1' => EmailBlock::LEVEL_H1,
                    'H2' => EmailBlock::LEVEL_H2,
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'label.email_block_content',
                'help' => 'label.email_block_content_help',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('label', TextType::class, [
                'label' => 'label.email_block_label',
                'help' => 'label.email_block_label_help',
                'required' => false,
            ])
            ->add('url', TextType::class, [
                'label' => 'label.email_block_url',
                'help' => 'label.email_block_url_help',
                'required' => false,
            ])
            ->add('alt', TextType::class, [
                'label' => 'label.email_block_alt',
                'help' => 'label.email_block_alt_help',
                'required' => false,
            ])
            ->add('height', IntegerType::class, [
                'label' => 'label.email_block_height',
                'help' => 'label.email_block_height_help',
                'required' => false,
            ])
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ])
        ;

        // Added via PRE_SET_DATA, not statically above, since the entry's actual data isn't bound yet when buildForm() itself runs
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event): void {
                $block = $event->getData();
                $form = $event->getForm();

                CollectionReconciler::addIdField($form, $block instanceof EmailBlock ? $block->getId() : null);

                // A saved data block keeps the kind it is and the fragment it names. Both are locked rather than merely hidden, retyping a "slot" into a "text" being a deletion by another road - and a disabled field is neither submitted nor overwritten. Only saved ones: the choice list still offers both kinds, which is how an admin adds a slot a bundle update has started declaring
                if ($block instanceof EmailBlock && null !== $block->getId() && $block->isDataBlock()) {
                    $this->lock($form, 'type');
                    $this->lock($form, 'label');
                }
            }
        );
    }

    // Re-adds one field with the same type and options it was built with, plus "disabled" - the only way to change an option once buildForm() has run
    private function lock(FormInterface $form, string $name): void
    {
        $child = $form->get($name);
        $config = $child->getConfig();

        $form->add($name, $config->getType()->getInnerType()::class, ['disabled' => true] + $config->getOptions());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EmailBlock::class,
            'label' => false,
            'translation_domain' => 'ui',
        ]);
    }
}
