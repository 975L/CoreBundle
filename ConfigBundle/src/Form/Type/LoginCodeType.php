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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

// The six digits of LoginCodeController, offered by the phone's keyboard straight from the email
class LoginCodeType extends AbstractType
{
    // One field, checked by LoginCode rather than here
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'label.login_code',
            'translation_domain' => 'config',
            'attr' => ['inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'maxlength' => 6, 'pattern' => '[0-9]{6}', 'autofocus' => true],
            'constraints' => [new NotBlank()],
        ]);
    }
}
