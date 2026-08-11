<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Form\MediaUploadType;
use c975L\UiBundle\Registry\BlockRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlockFormController extends AbstractController
{
    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    #[Route('/ui/block/data-form', name: 'ui_block_data_form', methods: ['GET', 'POST'])]
    public function dataForm(Request $request): Response
    {
        $kind = (string) $request->query->get('k', '');
        if (!$kind || !$this->registry->has($kind)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Duplicating a block (see block-duplicate.js) posts its current "data" field values here, so the sub-form comes back pre-filled instead of empty. Deliberately NOT extended to "medias": MediaUploadType has `data_class: Media`, and Symfony's form component requires a form's view data to be an actual instance of that class (or null) when one is set - passing it a plain array throws ("the form's view data is expected to be an instance of Media, but is an array"). That only works for "data" because kind-specific form types (SliderType etc.) have `data_class: null`. Media duplication is instead handled entirely client-side.
        $submittedData = null;
        if ($request->isMethod('POST') && $request->request->has('data')) {
            $data = $request->request->all('data');
            // An "id" identifies THIS block and this one only - whether auto-generated (SliderType/ImageCompareType) or an anchor typed by the editor (CardType/ReadmoreType) - so it is never carried over to the copy, which would put two elements with the same id in the page (an html validation error, and a Stimulus controller targeting the wrong section). Dropped here rather than in block-duplicate.js so it holds for every caller, and so the kind-specific type's own PRE_SET_DATA regenerates one
            unset($data['id']);
            $submittedData = ['data' => $data];
        }

        // This form is only ever rendered, never persisted from here: CSRF would add a root-level "invalid token" error to the duplication submit() below (no token is posted, and none is needed for a read-only preview), and validation would decorate the freshly-duplicated fields with violations (constraints, plus the "extra fields" one for any posted key the type doesn't declare) before the editor even touched them - they are reported for real when the whole EasyAdmin form is submitted
        $builder = $this->formFactory
            ->createNamedBuilder('_block_', FormType::class, null, [
                'translation_domain' => 'ui',
                'csrf_protection' => false,
                'validation_groups' => false,
            ])
            // Same prefix BlockType gives its own "data" sub-form, so the fragment this returns is laid out by the very block that lays out the edit screen (see form/block_theme.html.twig, "ui_block_data_widget") - the two would otherwise disagree the moment a kind groups its fields
            ->add('data', $this->registry->getFormClass($kind), ['label' => false, 'block_prefix' => 'ui_block_data']);

        if ($this->registry->hasMediaTypes($kind)) {
            $accept = implode(',', $this->registry->getMediaTypes($kind));

            $builder->add('medias', CollectionType::class, [
                'label' => 'label.media',
                'help' => $this->registry->getMediaHelp($kind),
                'entry_type' => MediaUploadType::class,
                'entry_options' => ['accept' => $accept, 'context' => $kind],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ]);

            // Mirrors BlockType::addMediaSubForm() - only relevant here so the AJAX-loaded preview (picking a kind on a brand new block) shows the same multi-upload input right away
            if ($this->registry->allowsMultiUpload($kind)) {
                $builder->add('mediaUpload', FileType::class, [
                    'label' => 'label.media_multi_upload',
                    'help' => 'label.media_multi_upload_help',
                    'required' => false,
                    'multiple' => true,
                    'attr' => array_filter(['accept' => $accept]),
                ]);
            }
        }

        // Mirrors BlockType::addSlotsSubForm(), so a brand new container shows its "add a slot" collection at once
        if ($this->registry->isContainer($kind)) {
            $builder->add('slots', CollectionType::class, [
                'label' => 'label.slots',
                'entry_type' => BlockType::class,
                'entry_options' => ['context' => $this->registry->getSlotContext($kind)],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ]);
        }

        $form = $builder->getForm();

        // Submitted rather than passed as the builder's initial data: what block-duplicate.js posts is raw request strings, while initial data is model data and goes through each field's transformers the other way round - a checked CheckboxType would get the string "1" where BooleanToStringTransformer::transform() demands a real bool ("Unable to transform value for property path "[primary]": Expected a Boolean."), and the same mismatch waits for every non-string field (dates, times...). Submitting instead reverse-transforms those strings exactly as a normal form post does. `clearMissing: false` so the fields the payload doesn't carry (the "id" dropped above, "medias"/"slots") keep the defaults the kind-specific type's own PRE_SET_DATA just gave them, instead of being wiped
        if (null !== $submittedData) {
            $form->submit($submittedData, false);
        }

        return $this->render('@c975LUi/form/block.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
