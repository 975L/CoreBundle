<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Service\BlockAnchorSlugger;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

// Opt-in for any "Page sections" kind FormType wanting a menu-targetable in-page anchor - see UiBundle/README.md "Anchors (in-page navigation)". Call addAnchorField() from buildForm(), injecting BlockAnchorSlugger in the FormType's own constructor (autowired, no services.yaml entry needed thanks to the bundle's "resource: '../src/'" scan - works the same from any other c975L bundle's FormType as long as it requires c975l/ui-bundle).
trait HasAnchorFieldTrait
{
    private function addAnchorField(FormBuilderInterface $builder, BlockAnchorSlugger $anchorSlugger, string $titleField = 'title'): void
    {
        $builder->add('anchor', TextType::class, [
            'label' => 'label.anchor',
            'help' => 'label.anchor_help',
            'required' => false,
        ]);

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event) use ($anchorSlugger, $titleField): void {
                $data = $event->getData();
                $data['anchor'] = $anchorSlugger->slugify($data['anchor'] ?? null, $data[$titleField] ?? null);
                $event->setData($data);
            }
        );
    }
}
