<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Service\EmailTemplateRenderer;
use c975L\UiBundle\Twig\EmailTemplateExtension;
use PHPUnit\Framework\TestCase;

class EmailTemplateExtensionTest extends TestCase
{
    public function testRenderEmailTemplateBodyDelegatesToRendererWhenFound(): void
    {
        $renderer = $this->createStub(EmailTemplateRenderer::class);
        $renderer->method('renderNamedBody')->willReturnCallback(
            static fn (string $name, array $variables) => 'account_validation' === $name ? '<p>' . ($variables['signed_url'] ?? '') . '</p>' : null
        );

        $extension = new EmailTemplateExtension($renderer);

        $this->assertSame(
            '<p>https://example.test/verify</p>',
            $extension->renderEmailTemplateBody('account_validation', ['signed_url' => 'https://example.test/verify'])
        );
    }

    // A missing/renamed EmailTemplate must not break the email it's embedded into - only render nothing
    public function testRenderEmailTemplateBodyReturnsEmptyStringWhenNotFound(): void
    {
        $renderer = $this->createConfiguredStub(EmailTemplateRenderer::class, ['renderNamedBody' => null]);

        $this->assertSame('', new EmailTemplateExtension($renderer)->renderEmailTemplateBody('does_not_exist'));
    }

    // The recipient's language is what decides the version, and it reaches the renderer as it was given
    public function testTheRecipientsLanguageIsHandedOver(): void
    {
        $renderer = $this->createStub(EmailTemplateRenderer::class);
        $renderer->method('renderNamedBody')->willReturnCallback(
            static fn (string $name, array $variables, ?string $locale) => (string) $locale
        );

        $this->assertSame('es', new EmailTemplateExtension($renderer)->renderEmailTemplateBody('layout_hello', [], 'es'));
    }
}
