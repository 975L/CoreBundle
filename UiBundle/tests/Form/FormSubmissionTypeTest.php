<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Form\CaptchaType;
use c975L\UiBundle\Form\FormSubmissionType;
use c975L\UiBundle\Service\CaptchaVerifier;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Validator\Constraints\DnsEmail;
use PHPUnit\Framework\TestCase;
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
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormSubmissionTypeTest extends TestCase
{
    private function buildField(string $name, string $type, bool $required, ?string $placeholder = null, ?string $url = null): FormField
    {
        $field = new FormField();
        $field->setName($name);
        $field->setLabel(ucfirst($name));
        $field->setType($type);
        $field->setRequired($required);
        $field->setPlaceholder($placeholder);
        $field->setUrl($url);

        return $field;
    }

    private function createType(bool $recaptcha = false): FormSubmissionType
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnMap([
            ['recaptcha3-site-key', $recaptcha],
            ['recaptcha3-secret-key', $recaptcha],
        ]);
        $configService->method('get')->willReturnMap([
            ['recaptcha3-site-key', $recaptcha ? 'site-key' : null],
            ['recaptcha3-secret-key', $recaptcha ? 'secret-key' : null],
        ]);
        $configService->method('getContainerParameter')->willReturn(null);

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$request]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('read');

        $captchaVerifier = new CaptchaVerifier($this->createStub(HttpClientInterface::class), $configService, $requestStack);

        return new FormSubmissionType(new FormBotProtection($configService), $requestStack, $translator, $captchaVerifier);
    }

    private function buildAddedFields(array $fields, bool $offerReceiveCopy = false, bool $recaptcha = false, array $prefill = []): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        $this->createType($recaptcha)->buildForm($builder, ['fields' => $fields, 'offerReceiveCopy' => $offerReceiveCopy, 'prefill' => $prefill]);

        return $added;
    }

    public function testEachFieldTypeMapsToItsSymfonyFormType(): void
    {
        $added = $this->buildAddedFields([
            $this->buildField('name', FormField::TYPE_TEXT, false),
            $this->buildField('message', FormField::TYPE_TEXTAREA, false),
            $this->buildField('email', FormField::TYPE_EMAIL, false),
            $this->buildField('newsletter', FormField::TYPE_CHECKBOX, false),
            $this->buildField('secret', FormField::TYPE_PASSWORD, false),
            $this->buildField('website', FormField::TYPE_URL, false),
            $this->buildField('phone', FormField::TYPE_TEL, false),
            $this->buildField('quantity', FormField::TYPE_NUMBER, false),
            $this->buildField('birthdate', FormField::TYPE_DATE, false),
        ]);

        $this->assertSame(TextType::class, $added['name']['type']);
        $this->assertSame(TextareaType::class, $added['message']['type']);
        $this->assertSame(EmailType::class, $added['email']['type']);
        $this->assertSame(CheckboxType::class, $added['newsletter']['type']);
        $this->assertSame(PasswordType::class, $added['secret']['type']);
        $this->assertSame(UrlType::class, $added['website']['type']);
        $this->assertSame(TelType::class, $added['phone']['type']);
        $this->assertSame(NumberType::class, $added['quantity']['type']);
        $this->assertSame(DateType::class, $added['birthdate']['type']);
    }

    // "single_text" so a date field renders as one HTML5 input, not Symfony's default 3-select widget
    public function testDateFieldUsesSingleTextWidget(): void
    {
        $added = $this->buildAddedFields([$this->buildField('birthdate', FormField::TYPE_DATE, false)]);

        $this->assertSame('single_text', $added['birthdate']['options']['widget']);
    }

    // RepeatedType wraps two password sub-fields, it doesn't take the same flat options as every other field type
    public function testPasswordRepeatedFieldBuildsRepeatedType(): void
    {
        $added = $this->buildAddedFields([$this->buildField('plainPassword', FormField::TYPE_PASSWORD_REPEATED, true)]);

        $this->assertSame(RepeatedType::class, $added['plainPassword']['type']);
        $this->assertSame(PasswordType::class, $added['plainPassword']['options']['type']);
        $this->assertSame('PlainPassword', $added['plainPassword']['options']['first_options']['label']);
        $this->assertSame('label.password_confirm', $added['plainPassword']['options']['second_options']['label']);
    }

    // A repeated password field always means "set a new password" - enforce the same minimum policy ChangePasswordFormType already does, regardless of which Form uses it (register, or any admin-built one)
    public function testPasswordRepeatedFieldGetsStrengthConstraints(): void
    {
        $added = $this->buildAddedFields([$this->buildField('plainPassword', FormField::TYPE_PASSWORD_REPEATED, true)]);
        $constraints = $added['plainPassword']['options']['first_options']['constraints'];

        $this->assertInstanceOf(NotBlank::class, $constraints[0]);
        $this->assertInstanceOf(Length::class, $constraints[1]);
        $this->assertInstanceOf(PasswordStrength::class, $constraints[2]);
        $this->assertInstanceOf(NotCompromisedPassword::class, $constraints[3]);
    }

    // Without this, a browser's password manager treats the email+password pair as a login form and autofills the visitor's already-saved password for this site
    public function testPasswordFieldGetsNewPasswordAutocomplete(): void
    {
        $added = $this->buildAddedFields([$this->buildField('secret', FormField::TYPE_PASSWORD, false)]);

        $this->assertSame('new-password', $added['secret']['options']['attr']['autocomplete']);
    }

    // A textarea's default HTML "rows" is too short to be usable for a multi-line message field
    public function testTextareaFieldGetsRowsAttribute(): void
    {
        $added = $this->buildAddedFields([
            $this->buildField('message', FormField::TYPE_TEXTAREA, false),
            $this->buildField('name', FormField::TYPE_TEXT, false),
        ]);

        $this->assertSame(10, $added['message']['options']['attr']['rows']);
        $this->assertArrayNotHasKey('rows', $added['name']['options']['attr']);
    }

    public function testPasswordRepeatedFieldGetsNewPasswordAutocompleteOnBothSubFields(): void
    {
        $added = $this->buildAddedFields([$this->buildField('plainPassword', FormField::TYPE_PASSWORD_REPEATED, true)]);

        $this->assertSame('new-password', $added['plainPassword']['options']['first_options']['attr']['autocomplete']);
        $this->assertSame('new-password', $added['plainPassword']['options']['second_options']['attr']['autocomplete']);
    }

    public function testRequiredFieldGetsNotBlankConstraint(): void
    {
        $added = $this->buildAddedFields([$this->buildField('phone', FormField::TYPE_TEXT, true)]);

        $this->assertTrue($added['phone']['options']['required']);
        $this->assertCount(1, $added['phone']['options']['constraints']);
        $this->assertInstanceOf(NotBlank::class, $added['phone']['options']['constraints'][0]);
    }

    // A required checkbox needs IsTrue, not NotBlank - an unchecked box submits "false", which NotBlank does not consider blank
    public function testRequiredCheckboxFieldGetsIsTrueConstraint(): void
    {
        $added = $this->buildAddedFields([$this->buildField('newsletter', FormField::TYPE_CHECKBOX, true)]);

        $this->assertCount(1, $added['newsletter']['options']['constraints']);
        $this->assertInstanceOf(IsTrue::class, $added['newsletter']['options']['constraints'][0]);
    }

    // Every email field gets format + anti-bot DNS checks, required or not
    public function testEmailFieldAlwaysGetsEmailAndDnsEmailConstraints(): void
    {
        $required = $this->buildAddedFields([$this->buildField('email', FormField::TYPE_EMAIL, true)]);
        $this->assertCount(3, $required['email']['options']['constraints']);
        $this->assertInstanceOf(NotBlank::class, $required['email']['options']['constraints'][0]);
        $this->assertInstanceOf(Email::class, $required['email']['options']['constraints'][1]);
        $this->assertInstanceOf(DnsEmail::class, $required['email']['options']['constraints'][2]);

        $optional = $this->buildAddedFields([$this->buildField('email', FormField::TYPE_EMAIL, false)]);
        $this->assertCount(2, $optional['email']['options']['constraints']);
        $this->assertInstanceOf(Email::class, $optional['email']['options']['constraints'][0]);
        $this->assertInstanceOf(DnsEmail::class, $optional['email']['options']['constraints'][1]);
    }

    public function testOptionalFieldGetsNoConstraint(): void
    {
        $added = $this->buildAddedFields([$this->buildField('phone', FormField::TYPE_TEXT, false)]);

        $this->assertFalse($added['phone']['options']['required']);
        $this->assertSame([], $added['phone']['options']['constraints']);
    }

    public function testPlaceholderIsPassedAsAttrWhenSet(): void
    {
        $added = $this->buildAddedFields([$this->buildField('phone', FormField::TYPE_TEXT, false, '06...')]);

        $this->assertSame(['placeholder' => '06...'], $added['phone']['options']['attr']);
    }

    public function testLabelIsNeverTranslated(): void
    {
        $added = $this->buildAddedFields([$this->buildField('phone', FormField::TYPE_TEXT, false)]);

        $this->assertFalse($added['phone']['options']['translation_domain']);
        $this->assertSame('Phone', $added['phone']['options']['label']);
        $this->assertFalse($added['phone']['options']['label_html']);
    }

    // A field carrying a "url" (e.g. a CGU checkbox) gets an escaped <a> appended to its label instead of plain text
    public function testFieldWithUrlGetsHtmlLabelWithLink(): void
    {
        $added = $this->buildAddedFields([$this->buildField('cgu', FormField::TYPE_CHECKBOX, true, url: 'https://example.com/cgu')]);

        $this->assertTrue($added['cgu']['options']['label_html']);
        $this->assertSame('Cgu (<a href="https://example.com/cgu" target="_blank" rel="noopener">read</a>)', $added['cgu']['options']['label']);
    }

    // The label text itself is escaped before being embedded as raw HTML, so an admin-typed "<" in a label can't break out of it
    public function testFieldWithUrlEscapesLabelAndUrl(): void
    {
        $added = $this->buildAddedFields([$this->buildField('cgu', FormField::TYPE_CHECKBOX, true, url: 'https://example.com/cgu?a=1&b=2')]);

        $this->assertStringContainsString('href="https://example.com/cgu?a=1&amp;b=2"', $added['cgu']['options']['label']);
    }

    // Honeypot is unconditional - no config needed, same as contact/register/reset. With no fields/recaptcha/receiveCopy, it is the only field added
    public function testHoneypotFieldIsAlwaysAdded(): void
    {
        $added = $this->buildAddedFields([]);

        $this->assertCount(1, $added);
    }

    public function testReceiveCopyFieldAddedOnlyWhenOffered(): void
    {
        $this->assertArrayNotHasKey('receiveCopy', $this->buildAddedFields([], offerReceiveCopy: false));

        $added = $this->buildAddedFields([], offerReceiveCopy: true);
        $this->assertSame(CheckboxType::class, $added['receiveCopy']['type']);
        $this->assertFalse($added['receiveCopy']['options']['required']);
    }

    // The checkbox is the one protection field whose answer an action reads back (SendEmailFormAction turns it into the copy it sends), and FormController hands the action nothing but the form's own data - which an unmapped child never appears in. Left unmapped, every visitor asking for a copy silently got none. Driven over a real form rather than the builder stub above: "mapped" is honoured inside Symfony, so only an actual submission proves the answer arrives where the action looks for it.
    public function testReceiveCopyAnswerReachesTheSubmittedData(): void
    {
        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([$this->createType()], []))
            ->getFormFactory()
            ->create(FormSubmissionType::class, null, ['fields' => [], 'offerReceiveCopy' => true]);

        $form->submit(['receiveCopy' => '1']);

        $this->assertTrue($form->getData()['receiveCopy'] ?? null, 'The visitor checked "receive a copy" and the action was handed no such key, so no copy was ever sent.');
    }

    // A consent the visitor cannot refuse without giving up the form is not a consent (GDPR article 4.11), and answering them rests on the site's legitimate interest anyway: what they are owed is the information line Form.html.twig renders, never a box
    public function testNoGdprCheckboxIsEverAdded(): void
    {
        $this->assertArrayNotHasKey('gdpr', $this->buildAddedFields([]));
    }

    public function testCaptchaFieldAddedOnlyWhenBothKeysConfigured(): void
    {
        $this->assertArrayNotHasKey('captcha', $this->buildAddedFields([], recaptcha: false));

        $added = $this->buildAddedFields([], recaptcha: true);
        $this->assertSame(CaptchaType::class, $added['captcha']['type']);
    }

    // The action name is reported to Google alongside the token, letting the admin console tell a generic Form's submissions apart from contact/register/reset
    public function testCaptchaFieldCarriesTheUiFormActionName(): void
    {
        $added = $this->buildAddedFields([], recaptcha: true);

        $this->assertSame('ui_form', $added['captcha']['options']['action_name']);
    }

    // See FormPrefillHelper - a field matching a prefill key gets its value as initial data and becomes readonly (still submitted, just not editable), same UX as ContactFormBundle's own locked "subject" field
    public function testPrefilledFieldGetsDataAndReadonlyAttr(): void
    {
        $added = $this->buildAddedFields(
            [$this->buildField('subject', FormField::TYPE_TEXT, false)],
            prefill: ['subject' => 'About listing #42']
        );

        $this->assertSame('About listing #42', $added['subject']['options']['data']);
        $this->assertTrue($added['subject']['options']['attr']['readonly']);
    }

    public function testNonPrefilledFieldHasNoDataOrReadonlyAttr(): void
    {
        $added = $this->buildAddedFields(
            [$this->buildField('subject', FormField::TYPE_TEXT, false)],
            prefill: ['other' => 'ignored']
        );

        $this->assertArrayNotHasKey('data', $added['subject']['options']);
        $this->assertArrayNotHasKey('readonly', $added['subject']['options']['attr']);
    }
}
