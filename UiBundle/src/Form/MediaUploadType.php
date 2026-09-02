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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File as FileConstraint;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Vich\UploaderBundle\Form\Type\VichImageType;

class MediaUploadType extends AbstractType
{
    // Symfony validates against the type guessed from the file's own bytes, not the label the browser sent, and the two disagree on names the "accept" lists are written with: a real .wav is guessed "audio/x-wav", so declaring "audio/wav" rejected every .wav upload. Kept here rather than in the kinds' media_types, which the file dialog's own list is built from and has no use for the aliases
    private const array MIME_ALIASES = [
        'audio/wav' => ['audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
        'audio/ogg' => ['application/ogg'],
        'video/ogg' => ['application/ogg'],
    ];

    public function __construct(
        private readonly MediaDimensionsFiller $mediaDimensionsFiller,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $flags = $this->contextFlags($options);

        $this->addBaseFields($builder, $options, $flags);
        $this->addImageFields($builder, $flags);

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event) use ($flags, $options): void {
                $media = $event->getData();

                // Unmapped, only used server-side to reconcile submitted entries against existing rows by ID (see BlockType's PRE_SUBMIT listener) - positional/identity diffing is unreliable once nested dynamic sub-forms are involved. Must be added here with "data" set directly: setting it via setData() after a static add() gets overwritten by the default mapper for unmapped fields, which falls back to the field's original (empty) "data" option.
                $event->getForm()->add('id', HiddenType::class, [
                    'mapped' => false,
                    'required' => false,
                    'data' => $media instanceof Media ? $media->getId() : null,
                ]);

                // For an already-uploaded entry, go off its real mimetype rather than the block kind's static accept list: a Slider (accept "image/*,video/*") always has $flags['video'] true, which used to force every slide - image slides included - onto VichFileType and lose their thumbnail preview. A brand-new empty entry has no mimetype yet, so it falls back to the accept-based guess; it gets no preview either way until saved & reloaded.
                $mimeType = $media instanceof Media ? $media->getMimeType() : null;
                $useImageType = null !== $mimeType
                    ? str_starts_with($mimeType, 'image/')
                    : ($flags['image'] && !$flags['video']);

                $event->getForm()->add('file', $useImageType ? VichImageType::class : VichFileType::class, [
                    'label' => $this->fileLabel($flags['videoBlock'], $flags['poster'], $mimeType),
                    'required' => false,
                    'allow_delete' => true,
                    'download_label' => false,
                    'delete_label_translation_domain' => 'messages',
                    'attr' => array_filter(['accept' => $options['accept']]),
                    'constraints' => $this->mimeTypeConstraints($options['accept']),
                ]);
            }
        );
    }

    // What the context and the accepted types say the entry is, read once for the whole form
    // @return array<string, bool>
    private function contextFlags(array $options): array
    {
        $acceptedTypes = null !== $options['accept'] ? explode(',', $options['accept']) : [];

        return [
            'image' => in_array('image/*', $acceptedTypes, true),
            // Matches an explicit list ("video/mp4,video/webm...", see the "video" block kind) as well as the "video/*" wildcard
            'video' => [] !== preg_grep('#^video/#', $acceptedTypes),
            'slider' => 'slider' === $options['context'],
            // A "video" block's medias are just its video file and (optionally) an image used as the player's cover - none of the per-image display metadata applies to either of them, the block's own form already carries the player's width/height
            'videoBlock' => 'video' === $options['context'],
            // Same thing for a "video_iframe", whose single media is only ever the player's poster - imported from the platform or uploaded here (see VideoPosterImporter). Its own kind, not videoBlock, because there is no video file beside it to tell it apart from
            'poster' => 'video_iframe' === $options['context'],
            'cards' => 'card' === $options['context'],
            'bannerTitle' => 'banner_title' === $options['context'],
            'portfolioGrid' => 'portfolio_grid' === $options['context'],
            'pdf' => in_array('application/pdf', $acceptedTypes, true),
        ];
    }

    // The file itself and what every entry carries beside it
    private function addBaseFields(FormBuilderInterface $builder, array $options, array $flags): void
    {
        // Placeholder type, always overridden in the PRE_SET_DATA listener below once the entry's real data (and mimetype, for an existing upload) is known - added here first only so "file" keeps rendering as the form's first field (re-adding a field under the same name replaces it in place rather than moving it to the end).
        $builder
            ->add('file', VichFileType::class, [
                'label' => $this->fileLabel($flags['videoBlock'], $flags['poster'], null),
                'required' => false,
                'allow_delete' => true,
                'download_label' => false,
                'delete_label_translation_domain' => 'messages',
                'attr' => array_filter(['accept' => $options['accept']]),
                'constraints' => $this->mimeTypeConstraints($options['accept']),
            ])
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ]);

        // cssClasses applies to a Card's teaser image too (see templates/blocks/Card.html.twig), so it stays out of the "!$isCards" group below
        if ($flags['image'] && !$flags['videoBlock'] && !$flags['poster']) {
            $builder->add('cssClasses', ImageClassChoiceType::class);
        }

        // Lets the admin give a PDF a readable name (e.g. "Rapport annuel") - UiMediaNamer slugifies it into the stored/physical filename instead of the default "block-document_download-{id}". Distinct from "label" (a display caption, not filesystem-safe) - kept out of the $flags['image'] block above since it has no meaning for those kinds.
        if ($flags['pdf']) {
            $builder->add('name', TextType::class, [
                'label' => 'label.file_name',
                'help' => 'label.file_name_help',
                'required' => false,
            ]);
        }
    }

    // Per-image display metadata, only relevant when the uploaded file is an image
    private function addImageFields(FormBuilderInterface $builder, array $flags): void
    {
        // Per-image display metadata, only relevant when the uploaded file is an image - none of the rest applies to a Card's teaser image: alt comes from the card's own title, there's no caption/sizing/rights markup for a card teaser Field order/set kept in parity with MediaCrudController (the Media library's own edit form)
        if ($flags['image'] && !$flags['cards'] && !$flags['videoBlock'] && !$flags['poster']) {
            $builder->add('alt', TextType::class, [
                'label' => 'label.alt_text',
                'required' => false,
            ]);

            // Caption/positioning/rights fields make sense for a standalone Image block, not for a slide inside a Slider (no in-page position to control, no "above the caption" layout) nor for a BannerTitle's background image (it's decoration behind text, not a captioned figure). A portfolio_grid project card reuses "label" too, but as its title, not a figure caption - width/height/above (inline captioned-figure layout) don't apply to a grid card.
            if (!$flags['slider'] && !$flags['bannerTitle']) {
                $builder->add('label', TextType::class, array_filter([
                    'label' => $flags['portfolioGrid'] ? 'label.title' : 'label.caption',
                    'help' => $flags['portfolioGrid'] ? null : 'label.caption_help',
                    'required' => false,
                ], static fn ($v) => null !== $v));

                if (!$flags['portfolioGrid']) {
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
            if ($flags['portfolioGrid']) {
                $builder
                    ->add('description', TextareaType::class, [
                        'label' => 'label.description',
                        'required' => false,
                    ])
                    // TextType and not UrlType, same as the block's own "linkUrl" (see PortfolioGridType): UrlType renders an <input type="url">, which the browser refuses to submit for anything but an absolute url - and a project card links to this very site as often as elsewhere ("/demo/", "/pages/blocks/Site"). The whole page form was then blocked client-side, with no message anywhere and the tab's error badge as the only sign
                    ->add('url', TextType::class, [
                        'label' => 'label.url',
                        'required' => false,
                    ]);
            }

            if (!$flags['bannerTitle']) {
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
    }

    // The "accept" attribute is a hint to the file dialog and nothing more - it never reached the server, and mobile-file-accept.js now drops it outright on touch devices, where an images-only list makes Android open its photo picker (gallery only, no Drive, no third-party storage provider). This is where the kind's declared media types are actually enforced, on both upload paths: the multi-file input is spliced into this same collection before mapping (see BlockType::mergeMultiUpload()). Symfony's File constraint reads the "image/*" wildcards the tags are written with as-is, so the tag's list is passed through untouched.
    private function mimeTypeConstraints(?string $accept): array
    {
        $declared = array_filter(array_map(trim(...), explode(',', (string) $accept)));
        if ([] === $declared) {
            return [];
        }

        $mimeTypes = $declared;
        foreach ($declared as $mimeType) {
            $mimeTypes = array_merge($mimeTypes, self::MIME_ALIASES[$mimeType] ?? []);
        }

        return [new FileConstraint(mimeTypes: array_values(array_unique($mimeTypes)))];
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
    private function fileLabel(bool $isVideoBlock, bool $isPoster, ?string $mimeType): string | bool
    {
        // A "video_iframe" has no video file of its own to be told apart from - the platform holds it - so its one row is named for what it is, whether it has been filled yet or not
        if ($isPoster) {
            return 'label.video_poster';
        }

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
