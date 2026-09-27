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
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

// The member's new address, on their own page: asked with the current password, as a password change is, the address becoming the way into the account (see EmailChanger)
class AccountEmailType extends AbstractType
{
    // Neither field is mapped, the controller handing the address to EmailChanger itself
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'label.current_password',
                'translation_domain' => 'config',
                'mapped' => false,
                // Chrome fills a saved password whatever "off" says, then the field before or after it as the login: "new-password" is the one value it leaves empty
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(message: 'text.password_required'),
                    new UserPassword(message: 'text.current_password_wrong'),
                ],
            ])
            ->add('newEmail', EmailType::class, [
                'label' => 'label.new_email',
                'translation_domain' => 'config',
                'mapped' => false,
                'attr' => ['autocomplete' => 'off'],
                'constraints' => [
                    new NotBlank(message: 'text.email_required'),
                    new Email(message: 'text.email_invalid'),
                    new Length(max: 180),
                ],
            ]);
    }

    // Left empty on every display: "email" would have the browser offer the current address back, and the form restored on a reload would hold it too
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('attr', ['autocomplete' => 'off']);
    }
}
