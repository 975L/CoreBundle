<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Form;

use c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Entity\Item;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// One field per case of the form rules, read by CodeRulesTestCaseTest
class ItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Bad: setName() refuses the null an emptied field sends
            ->add('name', TextType::class, ['label' => 'label.name'])
            ->add('code', TextType::class, ['empty_data' => ''])
            ->add('label', TextareaType::class)
            // Bad: replaces the saved stock on every edit
            ->add('stock', IntegerType::class, ['data' => 0])
            // Bad: same, through the html value
            ->add('price', NumberType::class, ['attr' => ['value' => 1]])
            ->add('extra', TextType::class, ['mapped' => false, 'data' => 'extra'])
            ->add('position', IntegerType::class, ['attr' => ['data-value' => 1, 'min' => 0]])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Item::class]);
    }
}
