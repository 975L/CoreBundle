<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckProviderInterface;
use c975L\UiBundle\Registry\EmailTemplateProviderRegistry;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Whether the site can actually send the transactional e-mails the installed bundles say it can.
 *
 * A template a bundle declares and the site has no row for still leaves: EmailTemplateRenderer::renderNamed() falls
 * back on the declaration itself, so no customer gets an empty envelope. What the site loses is the ability to edit
 * that wording at all - the back-office has nothing to show - which is worth saying out loud rather than letting an
 * admin conclude the editor is broken.
 *
 * A row emptied by an admin is the graver case and reads as one: there the envelope leaves with nothing in it.
 */
class EmailTemplateHealthCheckProvider implements HealthCheckProviderInterface
{
    public const string KIND = 'email-template';

    public function __construct(
        private readonly EmailTemplateProviderRegistry $emailTemplateProviderRegistry,
        private readonly EmailTemplateRepository $emailTemplateRepository,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
        /** @var string[] */
        #[Autowire(param: 'kernel.enabled_locales')]
        private readonly array $enabledLocales = [],
    ) {
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    public function runChecks(): array
    {
        $declared = $this->emailTemplateProviderRegistry->getDeclaredTemplates();
        if ([] === $declared) {
            return [];
        }

        $existing = [];
        foreach ($this->emailTemplateRepository->findAll() as $emailTemplate) {
            $existing[$emailTemplate->getName() . '|' . $emailTemplate->getLocale()] = \count($emailTemplate->getBlocks());
        }

        $results = [];
        foreach ($declared as $name => $blocksByLocale) {
            foreach ($this->localesFor($blocksByLocale) as $locale) {
                $results[] = $this->check($name, $locale, $existing[$name . '|' . $locale] ?? null);
            }
        }

        return $results;
    }

    /**
     * The languages this e-mail is expected to exist in: the site's own, plus every enabled one the declaring bundle
     * actually wrote. A language nobody wrote is not a gap - FormSeeder seeds none, and the renderer falls back.
     *
     * @param array<string, list<array<int, ?string>>> $blocksByLocale
     *
     * @return string[]
     */
    private function localesFor(array $blocksByLocale): array
    {
        $locales = [$this->defaultLocale];
        foreach ($this->enabledLocales as $locale) {
            if (isset($blocksByLocale[$locale])) {
                $locales[] = $locale;
            }
        }

        return array_unique($locales);
    }

    /** @return array<string, mixed> */
    private function check(string $name, string $locale, ?int $blocks): array
    {
        $label = $name . ' (' . $locale . ')';

        // Missing: the email still leaves, EmailTemplateRenderer falling back on the very wording this row would have been seeded from - but nobody can edit it in the back-office until the row exists, which is a warning rather than the silence this check was written for
        if (null === $blocks) {
            return [
                'url' => $name . '|' . $locale,
                'label' => $label,
                'status' => HealthCheckResult::STATUS_WARNING,
                'summary' => 'No row in this language: the email goes out with the wording its bundle declares, and cannot be edited here - run c975l:ui:email-templates:ensure',
                'details' => ['name' => $name, 'locale' => $locale],
            ];
        }

        // There, but empty: an admin emptied it, or it was seeded for a language its bundle ships no wording for. The envelope leaves, the page inside is blank
        if (0 === $blocks) {
            return [
                'url' => $name . '|' . $locale,
                'label' => $label,
                'status' => HealthCheckResult::STATUS_WARNING,
                'summary' => 'This template holds no block, so the email goes out empty',
                'details' => ['name' => $name, 'locale' => $locale],
            ];
        }

        return [
            'url' => $name . '|' . $locale,
            'label' => $label,
            'status' => HealthCheckResult::STATUS_OK,
            'summary' => $blocks . ' block(s)',
            'details' => ['name' => $name, 'locale' => $locale, 'blocks' => $blocks],
        ];
    }
}
