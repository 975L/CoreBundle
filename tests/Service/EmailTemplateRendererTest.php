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
use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Registry\EmailLayoutRegistry;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class EmailTemplateRendererTest extends TestCase
{
    private function createRenderer(string $siteUrl = 'https://example.test'): EmailTemplateRenderer
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'c975LUi');

        $configService = $this->createConfiguredStub(ConfigServiceInterface::class, ['get' => $siteUrl]);

        // No EmailLayoutProviderInterface registered - render() falls back to the standalone _wrapper.html.twig
        return new EmailTemplateRenderer(new Environment($loader), $configService, new EmailLayoutRegistry());
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
        $registry->addProvider(new class implements \c975L\UiBundle\Contract\EmailLayoutProviderInterface {
            public function wrap(string $bodyHtml): string
            {
                return '<div id="branded-layout">' . $bodyHtml . '</div>';
            }
        });

        $renderer = new EmailTemplateRenderer(new Environment($loader), $configService, $registry);

        $emailTemplate = new EmailTemplate();
        $this->addBlock($emailTemplate, EmailBlock::TYPE_HEADING)->setHeading('Hello');

        $html = $renderer->render($emailTemplate);

        $this->assertStringContainsString('id="branded-layout"', $html);
        $this->assertStringContainsString('Hello', $html);
        $this->assertStringNotContainsString('<!DOCTYPE', $html);
    }
}
