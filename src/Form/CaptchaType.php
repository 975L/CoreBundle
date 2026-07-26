<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use c975L\UiBundle\Service\CaptchaVerifier;
use c975L\UiBundle\Validator\Constraints\Captcha;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A hidden field carrying the reCAPTCHA v3 token, rendered by form/captcha_theme.html.twig and filled in by the "captcha" Stimulus controller - replaces karser/karser-recaptcha3-bundle's Recaptcha3Type. Unlike it, nothing is loaded from Google until the visitor actually touches the form (see assets/js/captcha.js)
class CaptchaType extends AbstractType
{
    public function __construct(private readonly CaptchaVerifier $captchaVerifier)
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['enabled'] = $this->captchaVerifier->isEnabled();
        $view->vars['site_key'] = $this->captchaVerifier->getSiteKey();
        $view->vars['action_name'] = $options['action_name'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // "action_name" is reported to Google alongside the token, letting the admin console break scores down per form
            'action_name' => 'form',
            'mapped' => false,
            'constraints' => [new Captcha()],
            // The token is machine-written: a visitor never sees this field, so it must never render a label, an asterisk or a "required" note (see components/Form/Form.html.twig)
            'required' => false,
            'label' => false,
        ]);

        $resolver->setAllowedTypes('action_name', 'string');
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'c975l_ui_captcha';
    }
}
