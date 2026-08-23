<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\EmailAttachmentProviderInterface;
use c975L\UiBundle\Model\EmailAttachment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

/**
 * Offers every legal model the site publishes as a file an email can carry.
 *
 * Why this exists at all: where the law asks for a "durable medium" - the terms a customer accepted, handed to
 * them at the moment of the sale - a link to a page is not one, the page being rewritable afterwards (art. L221-13
 * of the Code de la consommation, and CJEU C-49/11 on the hyperlink). A file in their mailbox is.
 *
 * Which emails carry which document stays an admin's answer, ticked in the email builder: this class only says
 * that the site is able to attach them, and draws the one asked for from the single source LegalDocument holds.
 */
class LegalDocumentAttachmentProvider implements EmailAttachmentProviderInterface
{
    // Namespaced so a kind is read at a glance in a template's row, and so a shop's "invoice" can never collide with a document of ours
    private const string PREFIX = 'legal:';

    public function __construct(
        private readonly LegalModelCatalog $legalModelCatalog,
        private readonly LegalDocument $legalDocument,
        private readonly TranslatorInterface $translator,
        private readonly SluggerInterface $slugger,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale = 'en',
    ) {
    }

    public function getAttachmentKinds(): array
    {
        $kinds = [];
        foreach ($this->legalModelCatalog->all() as $model) {
            $kinds[self::PREFIX . $model] = t($this->legalModelCatalog->label($model), [], 'ui');
        }

        return $kinds;
    }

    public function createAttachment(string $kind, array $context): ?EmailAttachment
    {
        $model = substr($kind, \strlen(self::PREFIX));

        if (!str_starts_with($kind, self::PREFIX) || !$this->legalModelCatalog->has($model)) {
            return null;
        }

        $locale = $context['locale'] ?? null;
        $locale = \is_string($locale) && '' !== $locale ? $locale : $this->defaultLocale;

        return new EmailAttachment($this->filename($model, $locale), $this->legalDocument->pdf($model, $locale));
    }

    // What the recipient sees the file called, in their own language: "conditions-generales-de-vente.pdf" and not "france-terms-of-sales.pdf"
    private function filename(string $model, string $locale): string
    {
        $label = $this->translator->trans($this->legalModelCatalog->label($model), [], 'ui', $locale);

        return $this->slugger->slug($label, '-', $locale)->lower() . '.pdf';
    }
}
