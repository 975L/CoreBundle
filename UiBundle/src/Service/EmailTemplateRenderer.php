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
use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Model\EmailAttachment;
use c975L\UiBundle\Registry\EmailAttachmentRegistry;
use c975L\UiBundle\Registry\EmailLayoutRegistry;
use c975L\UiBundle\Registry\EmailTemplateProviderRegistry;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Compiles an EmailTemplate's blocks into one email-safe HTML document; separate from render_block(), the email-safe vocabulary being deliberately closed
class EmailTemplateRenderer
{
    public function __construct(
        private readonly \Twig\Environment $twig,
        private readonly ConfigServiceInterface $configService,
        private readonly EmailLayoutRegistry $emailLayoutRegistry,
        private readonly EmailAttachmentRegistry $emailAttachmentRegistry,
        private readonly EmailTemplateRepository $emailTemplateRepository,
        private readonly EmailTemplateProviderRegistry $emailTemplateProviderRegistry,
        private readonly EmailTemplateFactory $emailTemplateFactory,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale = 'en',
    ) {
    }

    /**
     * Same as render(), for a template designated by name rather than held as an entity - what a bundle sending a
     * transactional email has (a fixed name it seeded, e.g. ConfigBundle's "account_validation"), instead of a
     * per-app Twig file it would have to know the path of.
     *
     * The site's own row first, the wording the bundle declares second (see declared()), null only when neither
     * exists - a name no installed bundle knows and no admin composed, which the caller decides the meaning of
     * rather than being handed a blank email.
     *
     * The locale is the recipient's, not the site's and not the request's: a reminder sent by a nightly command and
     * a shipping notice sent by the shopkeeper's click are both written to somebody who was never party to either
     * (see EmailTemplateRepository::findForRendering for what happens when that language has no version).
     *
     * @param array<string, scalar|array<string, mixed>> $variables see renderBody()
     */
    public function renderNamed(string $name, array $variables = [], ?string $locale = null): ?string
    {
        $emailTemplate = $this->emailTemplateRepository->findForRendering($name, $locale, $this->defaultLocale)
            ?? $this->declared($name, $locale);

        return null !== $emailTemplate ? $this->render($emailTemplate, $variables) : null;
    }

    /**
     * Same as renderNamed(), but just the rows - what a layout embeds inside its own html rather than sends whole.
     *
     * Resolved exactly like renderNamed(): the recipient's language first, the site's own next, the wording the
     * bundle declares last. Null when no version exists at all, which a caller renders as nothing.
     *
     * @param array<string, scalar|array<string, mixed>> $variables see renderBody()
     */
    public function renderNamedBody(string $name, array $variables = [], ?string $locale = null): ?string
    {
        $emailTemplate = $this->emailTemplateRepository->findForRendering($name, $locale, $this->defaultLocale)
            ?? $this->declared($name, $locale);

        return null !== $emailTemplate ? $this->renderBody($emailTemplate, $variables) : null;
    }

    /**
     * The files that same named template says it travels with, drawn.
     *
     * Read from the site's own row, which is the only place the answer lives: the wording a bundle declares is a
     * body and nothing else, so an email falling back on it (a row deleted in the back-office, see declared())
     * goes out on its own. That gap closes the moment c975l:ui:email-templates:ensure seeds the row again.
     *
     * @param array<string, mixed> $context what the providers are drawing about - see EmailAttachmentProviderInterface
     *
     * @return list<EmailAttachment>
     */
    public function attachmentsFor(string $name, array $context = [], ?string $locale = null): array
    {
        $emailTemplate = $this->emailTemplateRepository->findForRendering($name, $locale, $this->defaultLocale);

        if (null === $emailTemplate || [] === $emailTemplate->getAttachments()) {
            return [];
        }

        // Only merged when there is one to merge: "+" keeps the left-hand key, so an unset $locale would overwrite the one the caller already put in the context and send the terms of sale in the site's language instead of the reader's. Not $context + [...] either, which would ignore a locale the caller asked for outright
        return $this->emailAttachmentRegistry->resolve($emailTemplate->getAttachments(), (null !== $locale ? ['locale' => $locale] : []) + $context);
    }

    /**
     * The wording an installed bundle declares, built and rendered without ever reaching the database.
     *
     * What an email falls back on for the one gap c975l:ui:email-templates:ensure cannot close on its own: a row
     * deleted in the back-office, between that click and the next deployment. It is the very declaration the row
     * was seeded from, so the two can never say different things - which is what a Twig body sitting beside the
     * template could not promise, and why this bundle no longer ships one.
     */
    private function declared(string $name, ?string $locale): ?EmailTemplate
    {
        $blocksByLocale = $this->emailTemplateProviderRegistry->getDeclaredTemplates()[$name] ?? null;
        if (null === $blocksByLocale) {
            return null;
        }

        // Same order of preference as EmailTemplateRepository::findForRendering(): the recipient's language, the site's, then whichever the bundle happens to ship
        foreach ([$locale, $this->defaultLocale, array_key_first($blocksByLocale)] as $wanted) {
            if (null !== $wanted && [] !== ($blocksByLocale[$wanted] ?? [])) {
                return $this->emailTemplateFactory->build($name, $wanted, $blocksByLocale[$wanted]);
            }
        }

        return null;
    }

    /**
     * Full standalone document, wrapped through EmailLayoutProviderInterface when one is registered.
     *
     * @param array<string, scalar|array<string, mixed>> $variables see renderBody()
     */
    public function render(EmailTemplate $emailTemplate, array $variables = []): string
    {
        $blocksHtml = $this->renderBlocks($emailTemplate, $variables);

        return $this->emailLayoutRegistry->wrap($this->wrapBlocksInTable($blocksHtml), $emailTemplate->getLocale())
            ?? $this->twig->render('@c975LUi/emails/blocks/_wrapper.html.twig', ['blocksHtml' => $blocksHtml]);
    }

    /**
     * Just the compiled <tr> rows, wrapped in one <table> but with no surrounding <html>/<body> - meant to be
     * embedded inside an app/bundle's own email layout (e.g. SiteBundle's fullLayout.html.twig, which brings its
     * own Menu-driven header/footer - see c975L\UiBundle\Twig\EmailTemplateExtension::emailTemplateBody() and
     * EmailLayoutProviderInterface, its render()-time equivalent).
     *
     * @param array<string, scalar|array<string, mixed>> $variables resolves "{{ key }}" placeholders found in
     *                                                              heading/content/label/url/alt (see substitute() -
     *                                                              literal replacement, not real Twig evaluation),
     *                                                              plus an optional "fields" array consumed by any
     *                                                              EmailBlock::TYPE_FIELDS_TABLE block (e.g. a Form
     *                                                              submission's label => submitted value pairs, see
     *                                                              SendEmailFormAction), plus an optional "slots"
     *                                                              array of name => already-rendered html read by
     *                                                              EmailBlock::TYPE_SLOT blocks (see blockContext())
     */
    public function renderBody(EmailTemplate $emailTemplate, array $variables = []): string
    {
        return $this->wrapBlocksInTable($this->renderBlocks($emailTemplate, $variables));
    }

    /** @param string[] $blocksHtml */
    private function wrapBlocksInTable(array $blocksHtml): string
    {
        return sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0"><tbody>%s</tbody></table>',
            implode('', $blocksHtml)
        );
    }

    /** @return string[] */
    private function renderBlocks(EmailTemplate $emailTemplate, array $variables): array
    {
        $blocksHtml = [];
        foreach ($emailTemplate->getBlocks() as $block) {
            $blocksHtml[] = $this->twig->render($this->templateFor($block->getType()), $this->blockContext($block, $variables));
        }

        return $blocksHtml;
    }

    private function templateFor(string $type): string
    {
        return match ($type) {
            EmailBlock::TYPE_HEADING => '@c975LUi/emails/blocks/heading.html.twig',
            EmailBlock::TYPE_TEXT => '@c975LUi/emails/blocks/text.html.twig',
            EmailBlock::TYPE_HTML => '@c975LUi/emails/blocks/html.html.twig',
            EmailBlock::TYPE_BUTTON => '@c975LUi/emails/blocks/button.html.twig',
            EmailBlock::TYPE_IMAGE => '@c975LUi/emails/blocks/image.html.twig',
            EmailBlock::TYPE_DIVIDER => '@c975LUi/emails/blocks/divider.html.twig',
            EmailBlock::TYPE_SPACER => '@c975LUi/emails/blocks/spacer.html.twig',
            EmailBlock::TYPE_FIELDS_TABLE => '@c975LUi/emails/blocks/fields_table.html.twig',
            EmailBlock::TYPE_SLOT => '@c975LUi/emails/blocks/slot.html.twig',
            default => throw new \InvalidArgumentException(sprintf('Unknown EmailBlock type "%s"', $type)),
        };
    }

    private function blockContext(EmailBlock $block, array $variables): array
    {
        $url = $this->substitute($block->getUrl(), $variables);

        return [
            'heading' => $this->substitute($block->getHeading(), $variables),
            'level' => $block->getLevel() ?? EmailBlock::LEVEL_H2,
            'content' => $this->contentFor($block, $variables),
            'label' => $this->substitute($block->getLabel(), $variables),
            'url' => EmailBlock::TYPE_IMAGE === $block->getType() ? $this->resolveImageUrl($url) : $url,
            'alt' => $this->substitute($block->getAlt(), $variables),
            'height' => $block->getHeight() ?? 24,
            'fields' => $variables['fields'] ?? [],
            // Straight from the caller, past substitute() and past contentToHtml(): a slot is markup a bundle rendered, and escaping it or running admin-authored placeholders through it would either break it or make it the injection hole the rest of this class avoids
            'slot' => EmailBlock::TYPE_SLOT === $block->getType() ? ($variables['slots'][$block->getLabel()] ?? '') : '',
        ];
    }

    // A text block's content is prose turned into paragraphs and escaped; an html block's is markup written to be kept
    // Placeholder values are escaped either way, so a site name holding a "<" cannot open a tag the admin never wrote
    private function contentFor(EmailBlock $block, array $variables): string
    {
        if (EmailBlock::TYPE_HTML !== $block->getType()) {
            return $this->contentToHtml($this->substitute($block->getContent(), $variables));
        }

        return (string) $this->substitute($block->getContent(), array_map(
            static fn (mixed $value): mixed => is_scalar($value) ? htmlspecialchars((string) $value) : $value,
            $variables
        ));
    }

    // A stored path is prefixed with "site-url", so the domain lives in one place; an absolute url is left as-is
    private function resolveImageUrl(?string $url): ?string
    {
        if (null === $url || '' === $url || 1 === preg_match('#^(https?:)?//#i', $url)) {
            return $url;
        }

        return rtrim((string) $this->configService->get('site-url'), '/') . '/' . ltrim($url, '/');
    }

    // Literal replacement, never Twig evaluation: admin-authored text handed to Twig is a template injection hole
    private function substitute(?string $raw, array $variables): ?string
    {
        if (null === $raw || [] === $variables) {
            return $raw;
        }

        $map = [];
        foreach ($variables as $key => $value) {
            if (is_scalar($value)) {
                $map['{{ ' . $key . ' }}'] = (string) $value;
            }
        }

        return strtr($raw, $map);
    }

    // Escaped here, not by Twig's autoescape, so real <p>/<br> can be inserted; trusted HTML from here on
    private function contentToHtml(?string $raw): string
    {
        if (null === $raw || '' === trim($raw)) {
            return '';
        }

        $paragraphs = array_filter(
            preg_split('/\n\s*\n/', trim($raw)) ?: [],
            static fn (string $paragraph): bool => '' !== trim($paragraph)
        );

        return implode('', array_map(
            static fn (string $paragraph): string => sprintf(
                '<p style="margin:0 0 12px;">%s</p>',
                nl2br(htmlspecialchars($paragraph), false)
            ),
            $paragraphs
        ));
    }
}
