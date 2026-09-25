<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\MediaTranslator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File as FileConstraint;

// One media, said in one other language: a card's title and its text, a picture's caption, the alternative a screen reader announces, and a picture of its own when the image carries words (see MediaTranslator::stageFile). Its link, its credits and its dimensions are not here, a single value being read for every language (see BlockType, which puts this on a language screen).
class MediaTranslationType extends AbstractType
{
    // What each of the three fields is called on the screen, the same words the media's own form uses for them (see MediaUploadType)
    private const array LABELS = [
        'label' => 'label.title',
        'description' => 'label.description',
        'alt' => 'label.alt_text',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $media = $options['media'];

        // What each field opens on, handed as the sub-form's own data by BlockType: the language's text, or the source between brackets where it has none yet
        $values = (array) $builder->getData();

        // A field the media says nothing in has nothing to translate: the text it was written in is the msgid, and an empty one would offer a language a sentence the site itself never says
        foreach (MediaTranslator::FIELDS as $field) {
            $source = $media->getUntranslated($field);
            if (null === $source || '' === $source) {
                continue;
            }

            $builder->add($field, 'description' === $field ? TextareaType::class : TextType::class, [
                'label' => self::LABELS[$field],
                'data' => $values[$field] ?? null,
                // The text being translated, under the box it is translated into - the same landmark the menu screen puts there (see SiteBundle's TranslationController)
                'help' => $source,
                // The editor's own words, not a key: a "%" in a caption would otherwise pass for a parameter
                'help_translation_parameters' => [],
                'translation_domain' => 'ui',
                'required' => false,
                // Opt-in marker read by the block form theme, which is what puts the assistant under a plain field
                'attr' => ['data-ai-rephrase' => true],
            ]);
        }

        // A picture only: a PDF or a video is the same file in every language, and its own form is where it is replaced
        if (!str_starts_with((string) $media->getMimeType(), 'image/')) {
            return;
        }

        $builder->add('file', FileType::class, [
            'label' => 'label.translated_file',
            'help' => 'text.translated_file',
            'translation_domain' => 'ui',
            'required' => false,
            'constraints' => [new FileConstraint(mimeTypes: ['image/*'])],
            'attr' => ['accept' => 'image/*'],
        ]);

        if (null !== $options['translated_file']) {
            $builder->add('removeFile', CheckboxType::class, [
                'label' => 'label.translated_file_remove',
                'translation_domain' => 'ui',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            // The file this language already shows, if any: what the box taking it back is offered for
            ->setDefaults(['data_class' => null, 'translated_file' => null])
            ->setAllowedTypes('translated_file', ['null', 'string'])
            ->setRequired('media')
            ->setAllowedTypes('media', Media::class);
    }
}
