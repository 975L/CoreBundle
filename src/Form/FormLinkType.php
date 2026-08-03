<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Entry type for the "links" CollectionField of a Form - a plain label/url pair, rendered under the submit button by Form.html.twig (e.g. a "sign in" link under the register form). "data_class" null: an entry is an array stored in Form::$actionConfig, not an entity of its own, same shape as a Block's data sub-forms
class FormLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Text typed by the admin, shown as-is (no translation), like a FormField's own label
            ->add('label', TextType::class, [
                'label' => 'label.form_link_label',
            ])
            ->add('url', TextType::class, [
                'label' => 'label.form_link_url',
            ])
        ;
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
