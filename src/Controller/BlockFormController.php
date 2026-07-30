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
        private BlockRegistry $registry,
        private FormFactoryInterface $formFactory,
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
        $initialData = null;
        if ($request->isMethod('POST') && $request->request->has('data')) {
            $initialData = ['data' => $request->request->all('data')];
        }

        $builder = $this->formFactory
            ->createNamedBuilder('_block_', FormType::class, $initialData, ['translation_domain' => 'ui'])
            ->add('data', $this->registry->getFormClass($kind), ['label' => false]);

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

        return $this->render('@c975LUi/form/block.html.twig', [
            'form' => $builder->getForm()->createView(),
        ]);
    }
}
