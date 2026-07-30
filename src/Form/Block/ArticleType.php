<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArticleType extends AbstractType
{
    public function __construct(
        private readonly BlockAnchorSlugger $anchorSlugger,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => true,
            ])
            ->add('hook', TrixEditorType::class, [
                'label' => 'label.hook',
                'required' => false,
            ])
            ->add('content', TrixEditorType::class, [
                'label' => 'label.content',
                'required' => true,
            ])
            // Not user-editable: derived from the title below, used as the in-page anchor linked to from an articles_slider block
            ->add('slug', HiddenType::class, [
                'required' => false,
            ])
        ;

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event): void {
                $data = $event->getData();
                $data['slug'] = $this->anchorSlugger->slugify(null, $data['title'] ?? null) ?? '';
                $event->setData($data);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
