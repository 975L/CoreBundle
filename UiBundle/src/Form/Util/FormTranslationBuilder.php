<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Util;

use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Service\FormTranslator;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;

/**
 * The language screen of one form-builder row, shared by FormFieldType and FormOutputType.
 *
 * A row is offered as its translatable texts alone - what a language may change - each one unmapped so what is
 * written here lands in the translation table rather than overwriting the text the form was written in. The two
 * types differ only by which texts those are, which is what $labels carries.
 */
class FormTranslationBuilder
{
    public function __construct(
        private readonly FormTranslator $formTranslator,
    ) {
    }

    /**
     * @param array<string, string> $labels the row's translatable property => its own label key
     */
    public function build(FormBuilderInterface $builder, string $locale, array $labels): void
    {
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event) use ($locale, $labels): void {
                $row = $event->getData();

                // Rows are matched back by id, the same way the composing screen matches them - a language screen offers neither adding nor removing (see FormCrudController), so every row comes back and none is pruned
                CollectionReconciler::addIdField($event->getForm(), $row instanceof FormField || $row instanceof FormOutput ? $row->getId() : null);

                if (!$row instanceof FormField && !$row instanceof FormOutput) {
                    return;
                }

                $values = $this->formTranslator->promptValues($row, $locale);

                foreach ($labels as $property => $label) {
                    $event->getForm()->add($property, TextType::class, [
                        'label' => $label,
                        'required' => false,
                        'mapped' => false,
                        'data' => $values[$property] ?? null,
                    ]);
                }
            }
        );

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (PostSubmitEvent $event) use ($locale, $labels): void {
                $row = $event->getData();
                if (!$row instanceof FormField && !$row instanceof FormOutput) {
                    return;
                }

                $values = [];
                foreach (array_keys($labels) as $property) {
                    if ($event->getForm()->has($property)) {
                        $values[$property] = $event->getForm()->get($property)->getData();
                    }
                }

                // Staged rather than stored: this fires before the root form is validated, so a write here would keep a translation of a save about to be refused (see ContentTranslator::stage)
                $this->formTranslator->stage($row, $locale, $values);
            }
        );
    }
}
