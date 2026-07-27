<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\UiBundle\Form\Block;

use Symfony\Component\Form\AbstractType;
use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TextSectionType extends AbstractType
{
    use HasBackgroundFieldTrait;

    public function __construct(
        private readonly BlockAnchorSlugger $anchorSlugger
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('eyebrow', TextType::class, [
                'label'    => 'label.eyebrow',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label'    => 'label.title',
                'required' => false,
            ])
            // Not user-editable: derived from the title above, or from the eyebrow when there is none, used as the in-page anchor
            ->add('slug', HiddenType::class, [
                'required' => false,
            ])
            ->add('content', TrixEditorType::class, [
                'label' => 'label.content',
            ]);

        $this->addBackgroundField($builder);

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event): void {
                $data = $event->getData();
                // The eyebrow takes over as the slug's source when there is no title: it is then the section's own heading (see components/Text/Section.html.twig), and the in-page anchor must not vanish just because the heading was typed in the other field
                $data['slug'] = $this->anchorSlugger->slugify($data['title'] ?? null, $data['eyebrow'] ?? null) ?? '';
                $event->setData($data);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => null,
            'translation_domain' => 'ui',
        ]);
    }
}
