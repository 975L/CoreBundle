<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use c975L\UiBundle\Contract\EmailTemplateProviderInterface;
use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Service\FormSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The "register" and "reset_password_request" Forms and the two emails they send, seeded here rather than by SiteBundle's DefaultPagesImporter as they used to be: an app running Config+Ui plus a satellite bundle but no site foundation still needs an account to be creatable. Idempotent, so calling it again on an existing site is a no-op
class UserFormSeeder implements EmailTemplateProviderInterface
{
    // name => [type, label, url], one set per locale - FormSubmissionType renders FormField labels as literal text (translation_domain: false, an admin is expected to type real text, not a key), so these have to be actual words, picked once for kernel.default_locale since Form::$name is unique site-wide. "cgu"'s url points at that locale's own terms-of-use legal page, kept as a plain relative "/pages/{slug}" path (no router involved) since it's only ever read back once by FormSubmissionType - a site without those pages simply shows the label without a link
    private const array REGISTER_CORE_FIELDS = [
        'fr' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
            'plainPassword' => [FormField::TYPE_PASSWORD_REPEATED, 'Mot de passe', null],
            'cgu' => [FormField::TYPE_CHECKBOX, 'J\'accepte les conditions générales d\'utilisation', '/pages/conditions-generales-d-utilisation'],
        ],
        'en' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
            'plainPassword' => [FormField::TYPE_PASSWORD_REPEATED, 'Password', null],
            'cgu' => [FormField::TYPE_CHECKBOX, 'I accept the terms of use', '/pages/terms-of-use'],
        ],
        'es' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
            'plainPassword' => [FormField::TYPE_PASSWORD_REPEATED, 'Contraseña', null],
            'cgu' => [FormField::TYPE_CHECKBOX, 'Acepto las condiciones de uso', '/pages/condiciones-de-uso'],
        ],
    ];

    // Shown under the register form's submit button (see UiBundle's Form::getLinks()) - a visitor who already has an account, or who came here because they lost their password, would otherwise be stuck on a form that can't help them. Same locale keying and same plain-path convention as the "cgu" url above: "/login" is the scaffolded App\Controller\SecurityController route, the other one that locale's own default page (see SiteBundle's DefaultPagesImporter) - a site without that page simply has one dead link to fix or drop, the links staying fully editable
    private const array REGISTER_LINKS = [
        'fr' => [
            ['label' => 'J\'ai déjà un compte, me connecter', 'url' => '/login'],
            ['label' => 'Mot de passe oublié ?', 'url' => '/pages/mot-de-passe-oublie'],
        ],
        'en' => [
            ['label' => 'I already have an account, sign in', 'url' => '/login'],
            ['label' => 'Forgot your password?', 'url' => '/pages/forgot-password'],
        ],
        'es' => [
            ['label' => 'Ya tengo una cuenta, iniciar sesión', 'url' => '/login'],
            ['label' => '¿Ha olvidado su contraseña?', 'url' => '/pages/contrasena-olvidada'],
        ],
    ];

    // The way back out of the "I forgot my password" form, for a visitor who remembers it after all or who has no account yet
    private const array RESET_PASSWORD_REQUEST_LINKS = [
        'fr' => [
            ['label' => 'Retour à la connexion', 'url' => '/login'],
            ['label' => 'Créer un compte', 'url' => '/pages/creer-un-compte'],
        ],
        'en' => [
            ['label' => 'Back to sign in', 'url' => '/login'],
            ['label' => 'Create an account', 'url' => '/pages/register'],
        ],
        'es' => [
            ['label' => 'Volver al inicio de sesión', 'url' => '/login'],
            ['label' => 'Crear una cuenta', 'url' => '/pages/crear-una-cuenta'],
        ],
    ];

    // Same shape, for the "reset_password_request" Form - see the scaffolded App\Service\ResetPasswordRequestFormAction for the FormActionInterface key processing it
    private const array RESET_PASSWORD_REQUEST_CORE_FIELDS = [
        'fr' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
        ],
        'en' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
        ],
        'es' => [
            'email' => [FormField::TYPE_EMAIL, 'Email', null],
        ],
    ];

    // One EmailBlock tuple set per locale, unused positions left null. "{{ signed_url }}"/"{{ expires_at }}" are resolved by EmailVerifier at send time
    // The languages this bundle ships a config catalogue for. Listed rather than read from kernel.enabled_locales: the translator answers every locale by falling back on the default one, so iterating the site's languages would seed a Spanish row holding French sentences
    private const array LOCALES = ['fr', 'en', 'es'];

    /**
     * The two e-mails the account flow sends, as blocks an admin composes.
     *
     * Only the structure is written here, every sentence being read from the translation catalogue - the one place
     * this bundle's default wording lives, and what a translator edits for a language it does not ship yet. What an
     * admin rewrites afterwards is the seeded row, which neither this nor the catalogue ever overwrites.
     *
     * "{{ signed_url }}", "{{ reset_url }}" and "{{ expires_at }}" are resolved by EmailVerifier and by the
     * scaffolded ResetPasswordRequestFormAction; the last one is a whole block of its own, holding a placeholder and
     * no sentence, so it needs no key.
     *
     * @return array<string, array<string, list<array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}>>>
     */
    private function accountEmailBlocks(): array
    {
        $blocks = [];
        foreach (self::LOCALES as $locale) {
            $blocks[EmailVerifier::EMAIL_TEMPLATE][$locale] = [
                [EmailBlock::TYPE_HEADING, $this->trans('label.account_validation_heading', $locale), EmailBlock::LEVEL_H1, null, null, null],
                [EmailBlock::TYPE_TEXT, null, null, $this->trans('label.account_validation_text', $locale), null, null],
                [EmailBlock::TYPE_BUTTON, null, null, null, $this->trans('label.account_validation_button', $locale), '{{ signed_url }}'],
                [EmailBlock::TYPE_TEXT, null, null, '{{ expires_at }}', null, null],
            ];
            $blocks['password_reset'][$locale] = [
                [EmailBlock::TYPE_HEADING, $this->trans('label.password_reset_heading', $locale), EmailBlock::LEVEL_H1, null, null, null],
                [EmailBlock::TYPE_TEXT, null, null, $this->trans('label.password_reset_text', $locale), null, null],
                [EmailBlock::TYPE_BUTTON, null, null, null, $this->trans('label.password_reset_button', $locale), '{{ reset_url }}'],
                [EmailBlock::TYPE_TEXT, null, null, '{{ expires_at }}', null, null],
            ];
        }

        return $blocks;
    }

    private function trans(string $key, string $locale): string
    {
        return $this->translator->trans($key, [], 'config', $locale);
    }

    // The FormActionInterface key the register Form carries, and the only thing telling that Form apart from any other - seeded here, read back by RegistrationStatusProvider to answer whether accounts can still be created at all
    public const string REGISTER_ACTION = 'register';

    public function __construct(
        private readonly FormSeeder $formSeeder,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Declared as well as seeded: the same two definitions are what c975l:ui:email-templates:ensure brings to a site built before they existed, and what the health check reports missing
    public function getEmailTemplates(): array
    {
        return $this->accountEmailBlocks();
    }

    public function ensureRegisterForm(): void
    {
        $this->formSeeder->ensureForm('register', self::REGISTER_CORE_FIELDS, self::REGISTER_ACTION, linksByLocale: self::REGISTER_LINKS);
        $this->formSeeder->ensureEmailTemplate(EmailVerifier::EMAIL_TEMPLATE, $this->accountEmailBlocks()[EmailVerifier::EMAIL_TEMPLATE]);
    }

    public function ensureResetPasswordRequestForm(): void
    {
        $this->formSeeder->ensureForm('reset_password_request', self::RESET_PASSWORD_REQUEST_CORE_FIELDS, 'reset_password_request', linksByLocale: self::RESET_PASSWORD_REQUEST_LINKS);
        $this->formSeeder->ensureEmailTemplate('password_reset', $this->accountEmailBlocks()['password_reset']);
    }

    // Both flows in one go, flushed - what a caller with nothing else to seed wants (see c975l:config:user-create); a caller batching several seeds calls the two methods above and flushes once itself
    public function ensureAll(): void
    {
        $this->ensureRegisterForm();
        $this->ensureResetPasswordRequestForm();
        $this->entityManager->flush();
    }
}
