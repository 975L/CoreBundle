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
use c975L\UiBundle\Service\MediaDimensionsFiller;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Vich\UploaderBundle\Form\Type\VichImageType;

class MediaUploadType extends AbstractType
{
    public function __construct(
        private readonly MediaDimensionsFiller $mediaDimensionsFiller,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $acceptedTypes = null !== $options['accept'] ? explode(',', $options['accept']) : [];
        $isImage = in_array('image/*', $acceptedTypes, true);
        // Matches an explicit list ("video/mp4,video/webm...", see the "video" block kind) as well as the "video/*" wildcard
        $isVideo = [] !== preg_grep('#^video/#', $acceptedTypes);
        $isSlider = 'slider' === $options['context'];
        // A "video" block's medias are just its video file and (optionally) an image used as the player's cover - none of the per-image display metadata below applies to either of them, the block's own form already carries the player's width/height
        $isVideoBlock = 'video' === $options['context'];
        $isCards = 'card' === $options['context'];
        $isBannerTitle = 'banner_title' === $options['context'];
        $isPortfolioGrid = 'portfolio_grid' === $options['context'];
        $isPdf = in_array('application/pdf', $acceptedTypes, true);

        // Placeholder type, always overridden in the PRE_SET_DATA listener below once the entry's real data (and mimetype, for an existing upload) is known - added here first only so "file" keeps rendering as the form's first field (re-adding a field under the same name replaces it in place rather than moving it to the end).
        $builder
            ->add('file', VichFileType::class, [
                'label' => $this->fileLabel($isVideoBlock, null),
                'required' => false,
                'allow_delete' => true,
                'download_label' => false,
                'delete_label_translation_domain' => 'messages',
                'attr' => array_filter(['accept' => $options['accept']]),
            ])
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ]);

        // cssClasses applies to a Card's teaser image too (see templates/blocks/Card.html.twig), so it stays out of the "!$isCards" group below
        if ($isImage && !$isVideoBlock) {
            $builder->add('cssClasses', ImageClassChoiceType::class);
        }

        // Lets the admin give a PDF a readable name (e.g. "Rapport annuel") - UiMediaNamer slugifies it into the stored/physical filename instead of the default "block-document_download-{id}". Distinct from "label" (a display caption, not filesystem-safe) - kept out of the $isImage block above since it has no meaning for those kinds.
        if ($isPdf) {
            $builder->add('name', TextType::class, [
                'label' => 'label.file_name',
                'help' => 'label.file_name_help',
                'required' => false,
            ]);
        }

        // Per-image display metadata, only relevant when the uploaded file is an image - none of the rest applies to a Card's teaser image: alt comes from the card's own title, there's no caption/sizing/rights markup for a card teaser Field order/set kept in parity with MediaCrudController (the Media library's own edit form)
        if ($isImage && !$isCards && !$isVideoBlock) {
            $builder->add('alt', TextType::class, [
                'label' => 'label.alt_text',
                'required' => false,
            ]);

            // Caption/positioning/rights fields make sense for a standalone Image block, not for a slide inside a Slider (no in-page position to control, no "above the caption" layout) nor for a BannerTitle's background image (it's decoration behind text, not a captioned figure). A portfolio_grid project card reuses "label" too, but as its title, not a figure caption - width/height/above (inline captioned-figure layout) don't apply to a grid card.
            if (!$isSlider && !$isBannerTitle) {
                $builder->add('label', TextType::class, array_filter([
                    'label' => $isPortfolioGrid ? 'label.title' : 'label.caption',
                    'help' => $isPortfolioGrid ? null : 'label.caption_help',
                    'required' => false,
                ], static fn ($v) => null !== $v));

                if (!$isPortfolioGrid) {
                    $builder
                        ->add('width', TextType::class, [
                            'label' => 'label.width',
                            'help' => 'label.width_help',
                            'required' => false,
                        ])
                        ->add('height', TextType::class, [
                            'label' => 'label.height',
                            'help' => 'label.height_help',
                            'required' => false,
                        ])
                        ->add('above', CheckboxType::class, [
                            'label' => 'label.caption_above',
                            'required' => false,
                            // Bootstrap 5's native toggle-switch look (see bootstrap_5_layout.html.twig's checkbox_widget block) instead of a plain checkbox - same widget EasyAdmin's own BooleanField uses (BooleanConfigurator sets this same label_attr class)
                            'label_attr' => ['class' => 'checkbox-switch'],
                        ])
                        ->addEventListener(FormEvents::POST_SUBMIT, $this->refillDimensions(...));
                }
            }

            // A project card's own text and outbound link (see Media::$url/$description, reserved for this use case)
            if ($isPortfolioGrid) {
                $builder
                    ->add('description', TextareaType::class, [
                        'label' => 'label.description',
                        'required' => false,
                    ])
                    ->add('url', UrlType::class, [
                        'label' => 'label.url',
                        'required' => false,
                    ]);
            }

            if (!$isBannerTitle) {
                $builder
                    ->add('credits', TextType::class, [
                        'label' => 'label.credits',
                        'help' => 'label.credits_help',
                        'required' => false,
                    ])
                    ->add('rightsReserved', CheckboxType::class, [
                        'label' => 'label.rights_reserved',
                        'required' => false,
                        'label_attr' => ['class' => 'checkbox-switch'],
                    ]);
            }
        }

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event) use ($isImage, $isVideo, $isVideoBlock, $options): void {
                $media = $event->getData();

                // Unmapped, only used server-side to reconcile submitted entries against existing rows by ID (see BlockType's PRE_SUBMIT listener) - positional/identity diffing is unreliable once nested dynamic sub-forms are involved. Must be added here with "data" set directly: setting it via setData() after a static add() gets overwritten by the default mapper for unmapped fields, which falls back to the field's original (empty) "data" option.
                $event->getForm()->add('id', HiddenType::class, [
                    'mapped' => false,
                    'required' => false,
                    'data' => $media instanceof Media ? $media->getId() : null,
                ]);

                // For an already-uploaded entry, go off its real mimetype rather than the block kind's static accept list: a Slider (accept "image/*,video/*") always has $isVideo true, which used to force every slide - image slides included - onto VichFileType and lose their thumbnail preview. A brand-new empty entry has no mimetype yet, so it falls back to the accept-based guess; it gets no preview either way until saved & reloaded.
                $mimeType = $media instanceof Media ? $media->getMimeType() : null;
                $useImageType = null !== $mimeType
                    ? str_starts_with($mimeType, 'image/')
                    : ($isImage && !$isVideo);

                $event->getForm()->add('file', $useImageType ? VichImageType::class : VichFileType::class, [
                    'label' => $this->fileLabel($isVideoBlock, $mimeType),
                    'required' => false,
                    'allow_delete' => true,
                    'download_label' => false,
                    'delete_label_translation_domain' => 'messages',
                    'attr' => array_filter(['accept' => $options['accept']]),
                ]);
            }
        );
    }

    // An entry submitted with both dimension inputs blank keeps the size auto-detected on upload instead of erasing it - see MediaDimensionsFiller, which the media library's own form (MediaCrudController) shares
    private function refillDimensions(PostSubmitEvent $event): void
    {
        $media = $event->getData();

        if ($media instanceof Media) {
            $this->mediaDimensionsFiller->fillIfBlank($media);
        }
    }

    // Every other kind leaves its uploads unlabelled (a row is self-explanatory: one image among images, one PDF among PDFs), but a "video" block's two rows are two different things - the video file and the image used as the player's cover - and nothing else in the row says which is which, so each one is named after its own mimetype. A brand new row has no file yet, hence no mimetype: it's labelled with both, which is also what tells the admin a cover can be added at all
    private function fileLabel(bool $isVideoBlock, ?string $mimeType): string|bool
    {
        if (!$isVideoBlock) {
            return false;
        }

        if (null === $mimeType) {
            return 'label.video_file_or_poster';
        }

        return str_starts_with($mimeType, 'image/') ? 'label.video_poster' : 'label.video_file';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Media::class,
            'accept' => null,
            'context' => null,
        ]);

        $resolver->setAllowedTypes('accept', ['null', 'string']);
        $resolver->setAllowedTypes('context', ['null', 'string']);
    }
}
