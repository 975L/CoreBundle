<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Service\CaptchaVerifier;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Validator\Constraints\DnsEmail;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
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

// Builds a plain Symfony form from a c975L\UiBundle\Entity\Form's FormField collection - one input per field, keyed by FormField::getName(), unmapped to any entity (see FormController, which hands the submitted array straight to FormActionRegistry). Also adds the same protections every c975L bundle's own public forms already share: honeypot (always), GDPR/captcha (site-wide config, same keys contact/register/reset already read - see CaptchaType), receive-copy (per-Form, see Form::$actionConfig's "offerReceiveCopy")
class FormSubmissionType extends AbstractType
{
    public function __construct(
        private readonly FormBotProtection $botProtection,
        private readonly ConfigServiceInterface $configService,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly CaptchaVerifier $captchaVerifier,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->botProtection->addHoneypotField($builder, $this->requestStack->getCurrentRequest());

        foreach ($options['fields'] as $field) {
            $required = $field->isRequired();
            $prefilled = array_key_exists($field->getName(), $options['prefill']);

            // A required checkbox needs IsTrue, not NotBlank - an unchecked box submits "false", which NotBlank does not consider blank and would let through unenforced
            $constraints = [];
            if ($required) {
                $constraints[] = FormField::TYPE_CHECKBOX === $field->getType() ? new IsTrue(message: 'text.checkbox_required') : new NotBlank();
            }
            // Format first (cheap, EmailType's own HTML5 "type=email" attribute is client-side only), then the DNS/MX lookup on top
            if (FormField::TYPE_EMAIL === $field->getType()) {
                $constraints[] = new Email();
                $constraints[] = new DnsEmail();
            }

            // "translation_domain" false: a field's label is text the admin typed directly (see FormFieldType), not a translation key. When the field carries a "url" (e.g. a CGU checkbox pointing at the real terms-of-use page), the label is built as escaped HTML instead so a real <a> can be appended - see buildLabel()
            $fieldOptions = [
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
            if ($prefilled) {
                $fieldOptions['data'] = $options['prefill'][$field->getName()];
            }
            // A single HTML5 date input, not Symfony's default 3-select widget
            if (FormField::TYPE_DATE === $field->getType()) {
                $fieldOptions['widget'] = 'single_text';
            }

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
                continue;
            }

            $builder->add($field->getName(), $this->resolveFieldType($field->getType()), $fieldOptions);
        }

        if ($options['offerReceiveCopy']) {
            $builder->add('receiveCopy', CheckboxType::class, [
                'label' => 'label.receive_copy',
                'required' => false,
                'mapped' => false,
                'data' => false,
            ]);
        }

        if ($this->captchaVerifier->isEnabled()) {
            $builder->add('captcha', CaptchaType::class, [
                'action_name' => 'ui_form',
            ]);
        }

        // Falls back to true if "site-form-gdpr" isn't seeded, e.g. c975l/site-bundle isn't installed
        if ($this->configService->get('site-form-gdpr') ?? true) {
            $builder->add('gdpr', CheckboxType::class, [
                'label' => 'text.gdpr',
                'translation_domain' => 'site',
                'required' => true,
                'mapped' => false,
                // 'required' alone is HTML5-only - this is what actually rejects an unchecked box server-side. Same generic message as any other required checkbox field above, no GDPR-specific wording needed
                'constraints' => [
                    new IsTrue(message: 'text.checkbox_required'),
                ],
            ]);
        }
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
        ]);
        $resolver->setRequired('fields');
        $resolver->setAllowedTypes('fields', 'iterable');
        $resolver->setAllowedTypes('offerReceiveCopy', 'bool');
        $resolver->setAllowedTypes('prefill', 'array');
    }
}
