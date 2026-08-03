<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

// Shared honeypot + submission-timing anti-bot check, used by every public form that needs it (contact, registration, reset-password request...) so the heuristic lives in one place instead of being copy-pasted into each Form/Controller pair. Merges what used to be two separate implementations (c975L\SiteBundle\Service\FormBotProtection - fixed field name - and c975L\ContactFormBundle\Service\ContactFormService's own rotating honeypot) into one, keeping the rotating behavior.
class FormBotProtection
{
    private const SESSION_HONEYPOT_FIELD = 'ui_honeypot_field';
    private const SESSION_HONEYPOT_LABEL = 'ui_honeypot_label';

    private const HONEYPOT_NAME_PREFIXES = ['user', 'account', 'client', 'contact', 'person', 'profile'];
    private const HONEYPOT_NAME_SUFFIXES = ['name', 'info', 'data', 'field', 'input', 'details'];

    private const HONEYPOT_LABELS = [
        'Company website',
        'Your website',
        'Organization',
        'Department',
        'Job title',
        'Phone number',
        'Address',
        'City',
        'Postal code',
        'Country',
        'Fax number',
    ];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // Reads $sessionKey from session, lazily computing and stashing it via $generate() on first access - shared by honeypotFieldName()/honeypotLabel() below
    private function pickOnce(Request $request, string $sessionKey, callable $generate): string
    {
        $session = $request->getSession();
        if (null === $session->get($sessionKey)) {
            $session->set($sessionKey, $generate());
        }

        return $session->get($sessionKey);
    }

    // Picks a honeypot field name for this session, stable across the form's display/submit round-trip - harder for a bot to pattern-match across every site built on this than a single fixed field name
    public function honeypotFieldName(Request $request): string
    {
        return $this->pickOnce(
            $request,
            self::SESSION_HONEYPOT_FIELD,
            static fn (): string => self::HONEYPOT_NAME_PREFIXES[array_rand(self::HONEYPOT_NAME_PREFIXES)] . '_' . self::HONEYPOT_NAME_SUFFIXES[array_rand(self::HONEYPOT_NAME_SUFFIXES)]
        );
    }

    // Picks the honeypot field's visible label for this session, alongside honeypotFieldName()
    public function honeypotLabel(Request $request): string
    {
        return $this->pickOnce(
            $request,
            self::SESSION_HONEYPOT_LABEL,
            static fn (): string => self::HONEYPOT_LABELS[array_rand(self::HONEYPOT_LABELS)]
        );
    }

    // Honeypot, hidden inline so it needs no CSS framework: a non-empty value means a bot filled everything
    // $request is nullable, a caller outside a live HTTP request skipping the honeypot rather than crashing
    public function addHoneypotField(FormBuilderInterface $builder, ?Request $request): void
    {
        if (null === $request) {
            return;
        }

        $offscreen = 'position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;';
        $label = $this->honeypotLabel($request);

        $builder->add($this->honeypotFieldName($request), null, [
            'label' => $label,
            // The label is a decoy picked at random from HONEYPOT_LABELS, never seen by a human and meaningless to translate - without this, the form theme hands it to the translator, which reports one missing key per label the honeypot happens to pick (see ConfigBundle's c975l:dev-profile:run, which is what surfaced it)
            'translation_domain' => false,
            'label_attr' => ['style' => $offscreen],
            'required' => false,
            'mapped' => false,
            'data' => '',
            'row_attr' => ['style' => $offscreen],
            'attr' => [
                'placeholder' => $label,
                'autocomplete' => 'off',
                'tabindex' => '-1',
                'aria-hidden' => 'true',
            ],
        ]);
    }

    // Timestamps when the form was first displayed, to later measure how fast it was filled - call once, right after building the GET response, before isSuspicious()
    public function startTimer(Request $request, string $sessionKey): void
    {
        $session = $request->getSession();
        if (null === $session->get($sessionKey)) {
            $session->set($sessionKey, time());
        }
    }

    // Bot detection: honeypot field filled, or form submitted faster than a human could fill it (site-form-delay, in seconds). Reads the honeypot straight off the raw request instead of a submitted/validated FormInterface, so the caller can check this before running the Symfony form through handleRequest() - and skip that validation (DnsEmail's DNS/MX lookup included) entirely for a suspicious submission. Call once per submission, after startTimer() populated the same $sessionKey and addHoneypotField() added the honeypot field to the form built under $formName - the caller should silently redirect on true, with no hint to the bot
    public function isSuspicious(Request $request, string $formName, string $sessionKey): bool
    {
        $session = $request->getSession();
        $startedAt = (int) $session->get($sessionKey, 0);
        $session->remove($sessionKey);

        $honeypotValue = $request->request->all($formName)[$this->honeypotFieldName($request)] ?? null;
        $session->remove(self::SESSION_HONEYPOT_FIELD);
        $session->remove(self::SESSION_HONEYPOT_LABEL);

        // Falls back to 7s if "site-form-delay" isn't seeded, matching ContactFormBundle
        $formDelay = $this->configService->get('site-form-delay') ?? 7;

        return !empty($honeypotValue)
            || (time() - $startedAt) < $formDelay;
    }
}
