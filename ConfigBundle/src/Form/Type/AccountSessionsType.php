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
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;

// Signing the member out of every other device: the current password, hashed again by the controller, so every other session and remember-me cookie stops matching the account
class AccountSessionsType extends AbstractType
{
    // Not mapped, the controller handing it to PasswordResetter itself. "new-password" so Chrome leaves it empty, as on the address form
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('currentPassword', PasswordType::class, [
            'label' => 'label.current_password',
            'translation_domain' => 'config',
            'mapped' => false,
            'attr' => ['autocomplete' => 'new-password'],
            'constraints' => [
                new NotBlank(message: 'text.password_required'),
                new UserPassword(message: 'text.current_password_wrong'),
            ],
        ]);
    }
}
