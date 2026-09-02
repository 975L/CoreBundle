<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\EmailAttachmentProviderInterface;
use c975L\UiBundle\Contract\EmailLayoutProviderInterface;
use c975L\UiBundle\Contract\EmailTemplateProviderInterface;
use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Model\EmailAttachment;
use c975L\UiBundle\Registry\EmailAttachmentRegistry;
use c975L\UiBundle\Registry\EmailLayoutRegistry;
use c975L\UiBundle\Registry\EmailTemplateProviderRegistry;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use c975L\UiBundle\Service\EmailTemplateFactory;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function Symfony\Component\Translation\t;

class EmailTemplateRendererTest extends TestCase
{
    private function createRenderer(string $siteUrl = 'https://example.test'): EmailTemplateRenderer
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'c975LUi');

        $configService = $this->createConfiguredStub(ConfigServiceInterface::class, ['get' => $siteUrl]);

        // No EmailLayoutProviderInterface registered - render() falls back to the standalone _wrapper.html.twig
        return new EmailTemplateRenderer(new Environment($loader), $configService, new EmailLayoutRegistry(), new EmailAttachmentRegistry(), $this->createStub(EmailTemplateRepository::class), new EmailTemplateProviderRegistry(), new EmailTemplateFactory());
    }

    private function addBlock(EmailTemplate $emailTemplate, string $type): EmailBlock
    {
        $block = new EmailBlock();
        $block->setType($type);
        $emailTemplate->addBlock($block);

        return $block;
    }

    public function testRenderIncludesHeadingAndTextBlocks(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_HEADING)->setHeading('Welcome')->setLevel(EmailBlock::LEVEL_H1);
        $this->addBlock($emailTemplate, EmailBlock::TYPE_TEXT)->setContent("First paragraph.\n\nSecond paragraph.");

        $html = $this->createRenderer()->render($emailTemplate);

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Welcome', $html);
        $this->assertStringContainsString('<p style="margin:0 0 12px;">First paragraph.</p>', $html);
        $this->assertStringContainsString('<p style="margin:0 0 12px;">Second paragraph.</p>', $html);
    }

    public function testRenderSubstitutesPlaceholderVariablesInButtonUrl(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_BUTTON)
            ->setLabel('Confirm')
            ->setUrl('https://example.test/confirm?token={{ signed_url_token }}');

        $html = $this->createRenderer()->render($emailTemplate, ['signed_url_token' => 'abc123']);

        $this->assertStringContainsString('href="https://example.test/confirm?token=abc123"', $html);
        $this->assertStringNotContainsString('{{ signed_url_token }}', $html);
    }

    public function testRenderLeavesUnknownPlaceholdersUntouched(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_HEADING)->setHeading('Hello {{ unknown }}');

        $html = $this->createRenderer()->render($emailTemplate);

        $this->assertStringContainsString('Hello {{ unknown }}', $html);
    }

    public function testRenderEscapesHtmlInTextBlockContent(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_TEXT)->setContent('<script>alert(1)</script>');

        $html = $this->createRenderer()->render($emailTemplate);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // The layout is wrapped around a body already resolved in one language, and is told which - see EmailLayoutProviderInterface
    public function testRenderHandsTheTemplateLocaleToTheLayout(): void
    {
        $emailTemplate = new EmailTemplate();
        $emailTemplate->setLocale('es');
        $this->addBlock($emailTemplate, EmailBlock::TYPE_TEXT)->setContent('Hola');

        $provider = $this->createMock(EmailLayoutProviderInterface::class);
        $provider->expects($this->once())->method('wrap')->with($this->anything(), 'es')->willReturn('wrapped');

        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'c975LUi');
        $emailLayoutRegistry = new EmailLayoutRegistry();
        $emailLayoutRegistry->addProvider($provider);

        $renderer = new EmailTemplateRenderer(new Environment($loader), $this->createStub(ConfigServiceInterface::class), $emailLayoutRegistry, new EmailAttachmentRegistry(), $this->createStub(EmailTemplateRepository::class), new EmailTemplateProviderRegistry(), new EmailTemplateFactory());

        $this->assertSame('wrapped', $renderer->render($emailTemplate));
    }

    public function testRenderKeepsMarkupOfHtmlBlockContent(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_HTML)->setContent('Hello,<br><strong>welcome</strong>');

        $html = $this->createRenderer()->render($emailTemplate);

        $this->assertStringContainsString('Hello,<br><strong>welcome</strong>', $html);
        $this->assertStringNotContainsString('&lt;strong&gt;', $html);
    }

    // nl2br runs after raw, so the newline becomes a <br> and the markup around it is not escaped on the way
    public function testRenderTurnsNewlineOfHtmlBlockContentIntoLineBreak(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_HTML)->setContent("<strong>a</strong>\nb");

        $html = $this->createRenderer()->render($emailTemplate);

        $this->assertStringContainsString('<strong>a</strong><br />', $html);
        $this->assertStringNotContainsString('&lt;strong&gt;', $html);
    }

    // The markup is the admin's, the placeholder value is whoever's - so the second is escaped even here
    public function testRenderEscapesSubstitutedVariableValueInHtmlBlockContent(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_HTML)->setContent('<strong>{{ site }}</strong>');

        $html = $this->createRenderer()->render($emailTemplate, ['site' => '<script>alert(1)</script>']);

        $this->assertStringContainsString('<strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderEscapesHtmlInSubstitutedVariableValue(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_HEADING)->setHeading('Hi {{ name }}');

        $html = $this->createRenderer()->render($emailTemplate, ['name' => '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderFieldsTableRendersSubmittedLabelValuePairsAndEscapesThem(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_FIELDS_TABLE);

        $html = $this->createRenderer()->render($emailTemplate, ['fields' => ['Email' => 'visitor@example.test', 'Message' => '<b>hi</b>']]);

        $this->assertStringContainsString('Email', $html);
        $this->assertStringContainsString('visitor@example.test', $html);
        $this->assertStringContainsString('&lt;b&gt;hi&lt;/b&gt;', $html);
    }

    public function testRenderFieldsTableRendersNothingWhenNoFieldsGiven(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_FIELDS_TABLE);

        $html = $this->createRenderer()->render($emailTemplate);

        // fields_table.html.twig's own inner table (distinct style from the outer wrapper tables) must be absent
        $this->assertStringNotContainsString('border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;font-size:14px;', $html);
    }

    public function testRenderIncludesDividerAndSpacerBlocks(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_DIVIDER);
        $this->addBlock($emailTemplate, EmailBlock::TYPE_SPACER)->setHeight(40);

        $html = $this->createRenderer()->render($emailTemplate);

        $this->assertStringContainsString('border-top:1px solid', $html);
        $this->assertStringContainsString('height:40px', $html);
    }

    // A TYPE_IMAGE url stored as just a path is resolved against the single "site-url" config parameter, not hand-typed per block
    public function testRenderResolvesRelativeImageUrlAgainstSiteUrlConfig(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_IMAGE)->setUrl('/medias/logo.webp')->setAlt('Logo');

        $html = $this->createRenderer('https://mysite.test')->render($emailTemplate);

        $this->assertStringContainsString('src="https://mysite.test/medias/logo.webp"', $html);
    }

    // An already-absolute url (external/CDN image) is left untouched, not double-prefixed
    public function testRenderLeavesAbsoluteImageUrlUntouched(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_IMAGE)->setUrl('https://cdn.example.test/banner.png');

        $html = $this->createRenderer('https://mysite.test')->render($emailTemplate);

        $this->assertStringContainsString('src="https://cdn.example.test/banner.png"', $html);
    }

    // A protocol-relative url (also an external/CDN image) is left untouched too, not mistaken for a relative path
    public function testRenderLeavesProtocolRelativeImageUrlUntouched(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_IMAGE)->setUrl('//cdn.example.test/banner.png');

        $html = $this->createRenderer('https://mysite.test')->render($emailTemplate);

        $this->assertStringContainsString('src="//cdn.example.test/banner.png"', $html);
    }

    // Only TYPE_IMAGE's "url" is resolved against "site-url" - a button's url (routes, anchors, placeholders) must not be rewritten
    public function testRenderDoesNotResolveButtonUrlAgainstSiteUrlConfig(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_BUTTON)->setLabel('Go')->setUrl('/some/relative/path');

        $html = $this->createRenderer('https://mysite.test')->render($emailTemplate);

        $this->assertStringContainsString('href="/some/relative/path"', $html);
    }

    public function testRenderThrowsForUnknownBlockType(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, 'not_a_real_type');

        $this->expectException(\InvalidArgumentException::class);

        $this->createRenderer()->render($emailTemplate);
    }

    // renderBody() is the embeddable fragment: one <table>, no <!DOCTYPE>/<html>/<body>
    public function testRenderBodyOmitsDocumentWrapperButKeepsOneTable(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_HEADING)->setHeading('Hello');

        $html = $this->createRenderer()->renderBody($emailTemplate);

        $this->assertStringNotContainsString('<!DOCTYPE', $html);
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('<body', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function testRenderBodySubstitutesPlaceholderVariables(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_BUTTON)->setLabel('Reset')->setUrl('{{ reset_url }}');

        $html = $this->createRenderer()->renderBody($emailTemplate, ['reset_url' => 'https://example.test/reset/abc']);

        $this->assertStringContainsString('href="https://example.test/reset/abc"', $html);
    }

    // With a layout provider registered, render() must delegate to it instead of its own wrapper
    public function testRenderDelegatesToRegisteredEmailLayoutProvider(): void
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'c975LUi');
        $configService = $this->createConfiguredStub(ConfigServiceInterface::class, ['get' => 'https://example.test']);

        $registry = new EmailLayoutRegistry();
        $registry->addProvider(new class implements EmailLayoutProviderInterface {
            public function wrap(string $bodyHtml, ?string $locale = null): string
            {
                return '<div id="branded-layout">' . $bodyHtml . '</div>';
            }
        });

        $renderer = new EmailTemplateRenderer(new Environment($loader), $configService, $registry, new EmailAttachmentRegistry(), $this->createStub(EmailTemplateRepository::class), new EmailTemplateProviderRegistry(), new EmailTemplateFactory());

        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_HEADING)->setHeading('Hello');

        $html = $renderer->render($emailTemplate);

        $this->assertStringContainsString('id="branded-layout"', $html);
        $this->assertStringContainsString('Hello', $html);
        $this->assertStringNotContainsString('<!DOCTYPE', $html);
    }

    // What a bundle sending a transactional email has is the name it seeded, not the entity - and a template renamed or deleted from the back-office must be reported, not sent as a blank email
    public function testRenderNamedResolvesTheTemplateByNameAndReturnsNullWhenUnknown(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_HEADING)->setHeading('Hello');

        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'c975LUi');
        $configService = $this->createConfiguredStub(ConfigServiceInterface::class, ['get' => 'https://example.test']);

        $repository = $this->createStub(EmailTemplateRepository::class);
        $repository->method('findForRendering')->willReturnCallback(
            static fn (string $name): ?EmailTemplate => 'account_validation' === $name ? $emailTemplate : null
        );

        $renderer = new EmailTemplateRenderer(new Environment($loader), $configService, new EmailLayoutRegistry(), new EmailAttachmentRegistry(), $repository, new EmailTemplateProviderRegistry(), new EmailTemplateFactory());

        $this->assertStringContainsString('Hello', (string) $renderer->renderNamed('account_validation'));
        $this->assertNull($renderer->renderNamed('renamed_away'));
    }

    /**
     * The row deleted in the back-office, which is the one gap c975l:ui:email-templates:ensure cannot close on its own.
     *
     * What is rendered then is the declaration the row was seeded from, so the sentence a customer reads is the same
     * either way - the guarantee a Twig body sitting beside the template could never give.
     */
    public function testAMissingRowFallsBackOnTheWordingItsBundleDeclares(): void
    {
        $registry = new EmailTemplateProviderRegistry();
        $registry->addProvider(new class implements EmailTemplateProviderInterface {
            public function getEmailTemplates(): array
            {
                return ['password_reset' => [
                    'fr' => [['heading', 'Mot de passe oublié', 'h1', null, null, null]],
                    'en' => [['heading', 'Password forgotten', 'h1', null, null, null]],
                ]];
            }
        });

        $renderer = $this->rendererWithNoRows($registry);

        // The recipient's language first, the site's default when the bundle ships nothing in theirs
        $this->assertStringContainsString('Password forgotten', (string) $renderer->renderNamed('password_reset', [], 'en'));
        $this->assertStringContainsString('Mot de passe oublié', (string) $renderer->renderNamed('password_reset', [], 'fr'));
        $this->assertStringContainsString('Mot de passe oublié', (string) $renderer->renderNamed('password_reset', [], 'de'));
    }

    // A name an admin invented and no bundle declares still comes back null, SendEmailFormAction reading that as "use the Twig path this Form names"
    public function testANameNobodyDeclaresIsStillNull(): void
    {
        $this->assertNull($this->rendererWithNoRows(new EmailTemplateProviderRegistry())->renderNamed('invented_by_an_admin'));
    }

    // Which documents an email carries is read off the site's own row, beside the blocks that make up its body
    public function testANamedTemplateCarriesTheDocumentsItsRowSaysItDoes(): void
    {
        $attachments = $this->rendererFor(new EmailTemplate()->setAttachments(['invoice']))
            ->attachmentsFor('confirm_order', ['basket' => 'the order'], 'fr');

        $this->assertSame(['invoice.pdf'], array_map(static fn (EmailAttachment $a): string => $a->filename, $attachments));
    }

    // The recipient's language travels with the request, a document being drawn in the language the email is written in
    public function testTheLanguageTheEmailIsWrittenInReachesWhoeverDrawsTheDocument(): void
    {
        $seen = [];
        $renderer = $this->rendererFor(new EmailTemplate()->setAttachments(['invoice']), $seen);

        $renderer->attachmentsFor('confirm_order', ['basket' => 'the order'], 'de');

        $this->assertSame(['locale' => 'de', 'basket' => 'the order'], $seen);
    }

    // A caller naming the language in the context and not in the argument is not asking for the site's: merging the null over it would have drawn the terms of sale in the wrong language
    public function testALanguageCarriedByTheContextAloneSurvives(): void
    {
        $seen = [];
        $renderer = $this->rendererFor(new EmailTemplate()->setAttachments(['invoice']), $seen);

        $renderer->attachmentsFor('confirm_order', ['locale' => 'de', 'basket' => 'the order']);

        $this->assertSame(['locale' => 'de', 'basket' => 'the order'], $seen);
    }

    public function testATemplateTickingNothingTravelsAlone(): void
    {
        $this->assertSame([], $this->rendererFor(new EmailTemplate())->attachmentsFor('confirm_order'));
    }

    /**
     * A row deleted in the back-office: the wording a bundle declares is a body and nothing else, so the email goes
     * out on its own until c975l:ui:email-templates:ensure seeds the row again.
     */
    public function testAnEmailFallingBackOnADeclaredBodyTravelsAlone(): void
    {
        $this->assertSame([], $this->rendererWithNoRows(new EmailTemplateProviderRegistry())->attachmentsFor('confirm_order'));
    }

    // A renderer whose repository answers that row, and whose one provider draws an "invoice" recording what it was asked with
    private function rendererFor(EmailTemplate $emailTemplate, array &$seen = []): EmailTemplateRenderer
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'c975LUi');

        $repository = $this->createStub(EmailTemplateRepository::class);
        $repository->method('findForRendering')->willReturn($emailTemplate);

        $registry = new EmailAttachmentRegistry();
        $registry->addProvider(new class ($seen) implements EmailAttachmentProviderInterface {
            public function __construct(private array &$seen)
            {
            }

            public function getAttachmentKinds(): array
            {
                return ['invoice' => t('label.invoice', [], 'ui')];
            }

            public function createAttachment(string $kind, array $context): ?EmailAttachment
            {
                $this->seen = $context;

                return new EmailAttachment('invoice.pdf', '%PDF-1.7');
            }
        });

        return new EmailTemplateRenderer(
            new Environment($loader),
            $this->createConfiguredStub(ConfigServiceInterface::class, ['get' => 'https://example.test']),
            new EmailLayoutRegistry(),
            $registry,
            $repository,
            new EmailTemplateProviderRegistry(),
            new EmailTemplateFactory(),
            'fr'
        );
    }

    private function rendererWithNoRows(EmailTemplateProviderRegistry $registry): EmailTemplateRenderer
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'c975LUi');

        $repository = $this->createStub(EmailTemplateRepository::class);
        $repository->method('findForRendering')->willReturn(null);

        return new EmailTemplateRenderer(
            new Environment($loader),
            $this->createConfiguredStub(ConfigServiceInterface::class, ['get' => 'https://example.test']),
            new EmailLayoutRegistry(),
            new EmailAttachmentRegistry(),
            $repository,
            $registry,
            new EmailTemplateFactory(),
            'fr'
        );
    }

    // The one block whose html is written out as it came: a fragment a bundle rendered, never anything an admin typed
    public function testASlotBlockWritesTheFragmentTheCallerHandedOver(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_SLOT)->setLabel('items');

        $html = $this->createRenderer()->renderBody($emailTemplate, ['slots' => ['items' => '<table id="the-order"></table>']]);

        $this->assertStringContainsString('<table id="the-order"></table>', $html);
    }

    // An order carrying no gift card must not show the gap where one would have been: an empty fragment takes its row with it
    public function testASlotWithNothingInItRendersNothingAtAll(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_SLOT)->setLabel('gift_cards');

        $html = $this->createRenderer()->renderBody($emailTemplate, ['slots' => ['gift_cards' => '']]);

        $this->assertStringNotContainsString('<td', $html);
    }

    // A slot names a fragment, it does not name a placeholder: running admin-authored "{{ }}" through markup a bundle rendered is the injection hole the rest of this class exists to avoid
    public function testASlotIsNeverResolvedAgainstThePlaceholders(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_SLOT)->setLabel('items');

        $html = $this->createRenderer()->renderBody($emailTemplate, [
            'slots' => ['items' => '<p>{{ secret }}</p>'],
            'secret' => 'leaked',
        ]);

        $this->assertStringContainsString('{{ secret }}', $html);
        $this->assertStringNotContainsString('leaked', $html);
    }

    // The language is the recipient's, and it has to reach the lookup: a reminder sent by a nightly command carries the customer's, which is neither the request's nor the site's
    public function testRenderNamedHandsTheRecipientsLanguageToTheLookup(): void
    {
        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_HEADING)->setHeading('Hallo');

        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'c975LUi');
        $configService = $this->createConfiguredStub(ConfigServiceInterface::class, ['get' => 'https://example.test']);

        $asked = [];
        $repository = $this->createStub(EmailTemplateRepository::class);
        $repository->method('findForRendering')->willReturnCallback(
            function (string $name, ?string $locale, string $defaultLocale) use (&$asked, $emailTemplate): EmailTemplate {
                $asked = [$name, $locale, $defaultLocale];

                return $emailTemplate;
            }
        );

        $renderer = new EmailTemplateRenderer(new Environment($loader), $configService, new EmailLayoutRegistry(), new EmailAttachmentRegistry(), $repository, new EmailTemplateProviderRegistry(), new EmailTemplateFactory(), 'fr');
        $renderer->renderNamed('basket_reminder', [], 'de');

        $this->assertSame(['basket_reminder', 'de', 'fr'], $asked);
    }
}
