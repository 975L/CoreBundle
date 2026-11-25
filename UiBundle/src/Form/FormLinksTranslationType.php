<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Service\FormTranslator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The labels of a form's links on its language screen, one per link, unmapped so what is written lands in the translation table rather than over the label the form was written in; the urls are not offered, being localised where they render (see FormTranslator::getLinks())
class FormLinksTranslationType extends AbstractType
{
    public function __construct(
        private readonly FormTranslator $formTranslator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Form $form */
        $form = $options['ui_form'];
        $locale = (string) $options['translation_locale'];
        $values = $this->formTranslator->linkPromptValues($form, $locale);

        foreach ($form->getLinks() as $index => $link) {
            $builder->add((string) $index, TextType::class, [
                'label' => 'label.form_link_label',
                'help' => $link['url'],
                'required' => false,
                'data' => $values[$index] ?? null,
            ]);
        }

        // Staged rather than stored: this fires before the root form is validated (see ContentTranslator::stage)
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (PostSubmitEvent $event) use ($form, $locale): void {
            $labels = [];
            foreach ($event->getForm() as $index => $child) {
                $labels[(int) $index] = $child->getData();
            }

            $this->formTranslator->stageLinks($form, $locale, $labels);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
            'translation_domain' => 'ui',
        ]);
        $resolver->setRequired(['ui_form', 'translation_locale']);
        $resolver->setAllowedTypes('ui_form', Form::class);
        $resolver->setAllowedTypes('translation_locale', 'string');
    }
}
