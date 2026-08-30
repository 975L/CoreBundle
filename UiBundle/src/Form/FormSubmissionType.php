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
use c975L\UiBundle\Service\CaptchaVerifier;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Validator\Constraints\DnsEmail;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Contracts\Translation\TranslatorInterface;

// Builds a plain Symfony form from a c975L\UiBundle\Entity\Form's FormField collection - one input per field, keyed by FormField::getName(), unmapped to any entity (see FormController, which hands the submitted array straight to FormActionRegistry). Also adds the same protections every c975L bundle's own public forms already share: honeypot, captcha (site-wide config, same keys contact/register/reset already read - see CaptchaType), receive-copy (per-Form, see Form::$actionConfig's "offerReceiveCopy") - all three switched off by the "protections" option for a calculator, which submits nothing
class FormSubmissionType extends AbstractType
{
    public function __construct(
        private readonly FormBotProtection $botProtection,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly CaptchaVerifier $captchaVerifier,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // A calculator posts nothing and reaches no action (see Form::isCalculator()), so it gets none of the three: a honeypot to trap a submission that never happens, a captcha to score a visitor who only moved a slider, and a "receive a copy" box with no email to copy
        if ($options['protections']) {
            $this->botProtection->addHoneypotField($builder, $this->requestStack->getCurrentRequest());
        }

        foreach ($options['fields'] as $field) {
            $this->addField($builder, $field, $options['prefill']);
        }

        if ($options['offerReceiveCopy'] && $options['protections']) {
            // Mapped, unlike the captcha box below: this one is the only protection field whose answer an action has to read back, and FormController hands the action nothing but the form's own data - an unmapped child never appears there, so the copy was silently never sent
            $builder->add('receiveCopy', CheckboxType::class, [
                'label' => 'label.receive_copy',
                'required' => false,
                'data' => false,
            ]);
        }

        if ($options['protections'] && $this->captchaVerifier->isEnabled()) {
            $builder->add('captcha', CaptchaType::class, [
                'action_name' => 'ui_form',
            ]);
        }
    }

    // One declared field turned into its form child, the type deciding what it is given beside its label
    private function addField(FormBuilderInterface $builder, FormField $field, array $prefill): void
    {
        $required = $field->isRequired();
        $prefilled = array_key_exists($field->getName(), $prefill);
        $constraints = $this->fieldConstraints($field, $required);
        $fieldOptions = $this->fieldOptions($field, $required, $prefilled, $constraints);

        if ($prefilled) {
            $fieldOptions['data'] = $prefill[$field->getName()];
        }

        $this->applyTypeOptions($fieldOptions, $field, $prefilled);

        // RepeatedType wraps two sub-fields (its own "first_options"/"second_options"), it doesn't take the same flat options as every other field type. A repeated password field always means "set a new password" (unlike a plain TYPE_PASSWORD field, which could be re-entering an existing one) - Length/PasswordStrength/NotCompromisedPassword enforce the same minimum policy ChangePasswordFormType already does
        if (FormField::TYPE_PASSWORD_REPEATED === $field->getType()) {
            $builder->add($field->getName(), RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => $required,
                'first_options' => [
                    'label' => $field->getLabel(),
                    'translation_domain' => false,
                    'constraints' => [...$constraints, new Length(min: 8, max: 25), new PasswordStrength(), new NotCompromisedPassword()],
                    'attr' => array_merge($fieldOptions['attr'], ['autocomplete' => 'new-password']),
                ],
                'second_options' => ['label' => 'label.password_confirm', 'attr' => array_filter(['placeholder' => $field->getPlaceholder(), 'autocomplete' => 'new-password'])],
                'invalid_message' => 'text.password_mismatch',
            ]);

            return;
        }

        $builder->add($field->getName(), $this->resolveFieldType($field->getType()), $fieldOptions);
    }

    // What the submitted value is weighed against
    private function fieldConstraints(FormField $field, bool $required): array
    {
        $constraints = [];

        // A required checkbox needs IsTrue, not NotBlank - an unchecked box submits "false", which NotBlank does not consider blank and would let through unenforced
        if ($required) {
            $constraints[] = FormField::TYPE_CHECKBOX === $field->getType() ? new IsTrue(message: 'text.checkbox_required') : new NotBlank();
        }

        // Format first (cheap, EmailType's own HTML5 "type=email" attribute is client-side only), then the DNS/MX lookup on top
        if (FormField::TYPE_EMAIL === $field->getType()) {
            $constraints[] = new Email();
            $constraints[] = new DnsEmail();
        }

        return $constraints;
    }

    // What every field is given whatever its type: its label, whether it must be answered, and the attributes of its input
    // "translation_domain" false: a field's label is text the admin typed directly (see FormFieldType), not a translation key. When the field carries a "url" (e.g. a CGU checkbox pointing at the real terms-of-use page), the label is built as escaped HTML instead so a real <a> can be appended - see buildLabel()
    private function fieldOptions(FormField $field, bool $required, bool $prefilled, array $constraints): array
    {
        return [
            'label' => $this->buildLabel($field),
            'label_html' => null !== $field->getUrl(),
            'translation_domain' => false,
            'required' => $required,
            'constraints' => $constraints,
            // "readonly", not "disabled", so a prefilled field is still submitted
            // "new-password" stops a password manager autofilling this as a login form
            'attr' => array_filter([
                'placeholder' => $field->getPlaceholder(),
                'readonly' => $prefilled ?: null,
                'autocomplete' => FormField::TYPE_PASSWORD === $field->getType() ? 'new-password' : null,
                'rows' => FormField::TYPE_TEXTAREA === $field->getType() ? 10 : null,
            ]),
        ];
    }

    // What only some types are given: the bounds of a number, the pairs of a choice, the widget of a date, the value a calculator opens on
    private function applyTypeOptions(array &$fieldOptions, FormField $field, bool $prefilled): void
    {
        $this->applyNumericOptions($fieldOptions, $field);

        // A calculator shows a result before the visitor touches anything, which takes a starting value on every field - never overriding a prefill, which is the visitor's own data
        if (!$prefilled && null !== $field->getDefaultValue()) {
            if (FormField::TYPE_CHECKBOX === $field->getType()) {
                $fieldOptions['data'] = filter_var($field->getDefaultValue(), FILTER_VALIDATE_BOOLEAN);
            } elseif ($this->acceptsDefaultValue($field)) {
                $fieldOptions['data'] = $field->getDefaultValue();
            }
        }

        // A choice field's options are pairs the admin typed, the value being what an expression sees (e.g. 1.15 for "+15 %") - "choices" wants them the other way round
        if (FormField::TYPE_CHOICE === $field->getType()) {
            $fieldOptions['choices'] = array_column($field->getOptions(), 'value', 'label');
            $fieldOptions['placeholder'] = false;
        }

        // A single HTML5 date input, not Symfony's default 3-select widget
        if (FormField::TYPE_DATE === $field->getType()) {
            $fieldOptions['widget'] = 'single_text';
        }
    }

    // Bounds and increment are HTML attributes rather than constraints: they belong to a number/range input, and a calculator's slider is unusable without them
    private function applyNumericOptions(array &$fieldOptions, FormField $field): void
    {
        if (!in_array($field->getType(), [FormField::TYPE_NUMBER, FormField::TYPE_RANGE], true)) {
            return;
        }

        $fieldOptions['attr'] += array_filter([
            'min' => $field->getMinValue(),
            'max' => $field->getMaxValue(),
            'step' => $field->getStepValue(),
        ], static fn (?float $bound): bool => null !== $bound);

        // A real "type=number", not NumberType's default localised text input: a decimal typed on a fr site reaches the calculator as "8.2" rather than "8,2", and the min/max/step above stop being inert on what was a text input. Never for TYPE_RANGE, whose parent TextType declares no "html5" option. A "type=number" left without a step takes the browser's default of 1, which refuses a decimal
        if (FormField::TYPE_NUMBER === $field->getType()) {
            $fieldOptions['html5'] = true;
            $fieldOptions['attr']['step'] ??= 'any';
        }
    }

    // A default value is stored as a plain string, which only some types' transformer takes as is: a date wants a \DateTimeInterface and a number a numeric string, and both throw on render rather than on submit - setData() lets a TransformationFailedException through where submit() catches it, so an admin's stray default would 500 the public page
    private function acceptsDefaultValue(FormField $field): bool
    {
        return match ($field->getType()) {
            FormField::TYPE_NUMBER => is_numeric($field->getDefaultValue()),
            FormField::TYPE_TEXT, FormField::TYPE_TEXTAREA, FormField::TYPE_EMAIL, FormField::TYPE_URL, FormField::TYPE_TEL, FormField::TYPE_RANGE, FormField::TYPE_CHOICE => true,
            default => false,
        };
    }

    // Plain admin-typed text by default; with a "url" set, the label text stays exactly as typed but gains a translated, escaped "(label.field_url_link)" <a> - the surrounding label itself never becomes a link so clicking the rest of it still toggles a checkbox field as expected
    private function buildLabel(FormField $field): string
    {
        if (null === $field->getUrl()) {
            return $field->getLabel();
        }

        return sprintf(
            '%s (<a href="%s" target="_blank" rel="noopener">%s</a>)',
            htmlspecialchars($field->getLabel(), ENT_QUOTES),
            htmlspecialchars($field->getUrl(), ENT_QUOTES),
            htmlspecialchars($this->translator->trans('label.field_url_link', domain: 'ui'), ENT_QUOTES),
        );
    }

    private function resolveFieldType(string $type): string
    {
        return match ($type) {
            FormField::TYPE_TEXTAREA => TextareaType::class,
            FormField::TYPE_EMAIL => EmailType::class,
            FormField::TYPE_CHECKBOX => CheckboxType::class,
            FormField::TYPE_PASSWORD => PasswordType::class,
            FormField::TYPE_URL => UrlType::class,
            FormField::TYPE_TEL => TelType::class,
            FormField::TYPE_NUMBER => NumberType::class,
            FormField::TYPE_DATE => DateType::class,
            FormField::TYPE_RANGE => RangeType::class,
            FormField::TYPE_CHOICE => ChoiceType::class,
            default => TextType::class,
        };
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
            'offerReceiveCopy' => false,
            'prefill' => [],
            'protections' => true,
        ]);
        $resolver->setRequired('fields');
        $resolver->setAllowedTypes('fields', 'iterable');
        $resolver->setAllowedTypes('offerReceiveCopy', 'bool');
        $resolver->setAllowedTypes('prefill', 'array');
        $resolver->setAllowedTypes('protections', 'bool');
    }
}
