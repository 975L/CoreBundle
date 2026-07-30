<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Form\Util\CollectionReconciler;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

// Entry type for the "fields" CollectionField of a Form (see ContactFormBundle's ContactFormCrudController for a concrete usage), sortable via the admin-wide ea-sortable.js (targets any ".field-collection-item" row with a "position" input, nothing block-specific needed here) - unlike UiBundle's BlockType, every field type (text/textarea/email/checkbox) shares the exact same shape, so there is no per-kind dynamic sub-form to load
class FormFieldType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'label.field_label',
            ])
            ->add('placeholder', TextType::class, [
                'label' => 'label.field_placeholder',
                'required' => false,
            ])
            // Rendered as a link right after the label when set (e.g. a checkbox's "J'accepte les [CGU]") - see FormSubmissionType
            ->add('url', TextType::class, [
                'label' => 'label.field_url',
                'required' => false,
            ])
            ->add('required', CheckboxType::class, [
                'label' => 'label.field_required',
                'required' => false,
            ])
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ])
            // Always disabled: never editable through this form, only ever set by a seeding command (see a future FormDefaultsImporter) - a disabled field is never bound from submitted data (real server-side enforcement, not just hidden by CSS), rendered so ea-sortable.js can still read its current value client-side to hide that row's delete button (see assets/js/ea-sortable.js)
            ->add('restricted', CheckboxType::class, [
                'label' => false,
                'required' => false,
                'disabled' => true,
                'attr' => ['class' => 'ui-field-restricted d-none'],
            ])
        ;

        // "type" is added here, not statically above, so its "disabled" option can depend on this row's own data - like "restricted" itself, a disabled field ignores whatever is submitted and keeps its current value, so a restricted field can never be reclassified even by a tampered request, only reordered/relabelled
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event): void {
                $field = $event->getData();
                $restricted = $field instanceof FormField && $field->isRestricted();

                $event->getForm()->add('type', ChoiceType::class, [
                    'label' => 'label.field_type',
                    'choices' => self::typeChoices(),
                    'disabled' => $restricted,
                ]);

                CollectionReconciler::addIdField($event->getForm(), $field instanceof FormField ? $field->getId() : null);
            }
        );
    }

    // Shared with FormFieldTemplateCrudController's own "type" ChoiceField, so a template can only ever be set to one of the same real FormField::TYPE_* values - a single source of truth for the translated choice list instead of two copies drifting apart
    public static function typeChoices(): array
    {
        return [
            'label.field_type_text' => FormField::TYPE_TEXT,
            'label.field_type_textarea' => FormField::TYPE_TEXTAREA,
            'label.field_type_email' => FormField::TYPE_EMAIL,
            'label.field_type_checkbox' => FormField::TYPE_CHECKBOX,
            'label.field_type_password' => FormField::TYPE_PASSWORD,
            'label.field_type_password_repeated' => FormField::TYPE_PASSWORD_REPEATED,
            'label.field_type_url' => FormField::TYPE_URL,
            'label.field_type_tel' => FormField::TYPE_TEL,
            'label.field_type_number' => FormField::TYPE_NUMBER,
            'label.field_type_date' => FormField::TYPE_DATE,
        ];
    }

    // Same mapping as typeChoices(), reshaped for EasyAdmin's ChoiceField::setTranslatableChoices() (used by FormFieldTemplateCrudController's own "type" field): plain string choice keys only translate correctly when rendered inside a form whose own "translation_domain" is already "ui" (true for this form type itself, see configureOptions() below, but not for an EasyAdmin-generated CRUD form) - a t() object carries its own domain regardless of the surrounding form/CRUD's default one
    public static function translatableTypeChoices(): array
    {
        return array_combine(
            array_values(self::typeChoices()),
            array_map(static fn (string $labelKey): object => t($labelKey, [], 'ui'), array_keys(self::typeChoices())),
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FormField::class,
            'label' => false,
            'translation_domain' => 'ui',
        ]);
    }
}
