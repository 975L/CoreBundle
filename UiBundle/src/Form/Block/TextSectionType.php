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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TextSectionType extends AbstractType
{
    use HasBackgroundFieldTrait;

    // Matches the ".text-section--{tone}" modifier styled in sass/_page-sections.scss; "normal" is the block's own default and writes no class, so every section stored before this field existed goes on rendering as it did
    public const TONES = ['normal', 'secondary'];

    public function __construct(
        private readonly BlockAnchorSlugger $anchorSlugger,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('eyebrow', TextType::class, [
                'label' => 'label.eyebrow',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            // Not user-editable: derived from the title above, or from the eyebrow when there is none, used as the in-page anchor
            ->add('slug', HiddenType::class, [
                'required' => false,
            ])
            ->add('content', TrixEditorType::class, [
                'label' => 'label.content',
            ])
            // Body copy is what a section standing on its own is read at. Beside a louder one - the companion paragraph of a "text_hook" in the column next to it, a note beside the text it belongs to - the same size reads as a caption dropped in the corner, hence a step above body copy and a quieter color
            ->add('tone', ChoiceType::class, [
                'label' => 'label.text_tone',
                'help' => 'label.text_tone_help',
                'choices' => array_combine(
                    array_map(static fn (string $tone): string => 'label.text_tone_' . $tone, self::TONES),
                    self::TONES
                ),
                // No placeholder, same as every other stored-choice field: an unset value has to keep meaning "normal"
                'placeholder' => false,
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
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
