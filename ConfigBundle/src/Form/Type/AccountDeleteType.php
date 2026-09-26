<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

// The confirmation of AccountDeleteController: the account's own address typed again, which an OAuth account can do as well as one with a password
class AccountDeleteType extends AbstractType
{
    // One field, checked against the address of the account being deleted
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $expectedEmail = $options['expected_email'];

        $builder->add('email', EmailType::class, [
            'label' => 'label.account_delete_email',
            'translation_domain' => 'config',
            'attr' => ['autocomplete' => 'off'],
            'constraints' => [
                new NotBlank(),
                new Callback(static function (mixed $value, ExecutionContextInterface $context) use ($expectedEmail): void {
                    // Trimmed and case-insensitive: a capital typed on a phone is not a reason to refuse
                    if (null !== $value && 0 !== strcasecmp(trim((string) $value), $expectedEmail)) {
                        $context->buildViolation('text.account_delete_email_mismatch')->addViolation();
                    }
                }),
            ],
        ]);
    }

    // The address to type is required, the form meaning nothing without it
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('expected_email');
        $resolver->setAllowedTypes('expected_email', 'string');
    }
}
