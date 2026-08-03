<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use c975L\UiBundle\Twig\EmailTemplateExtension;
use PHPUnit\Framework\TestCase;

class EmailTemplateExtensionTest extends TestCase
{
    public function testRenderEmailTemplateBodyDelegatesToRendererWhenFound(): void
    {
        $emailTemplate = new EmailTemplate();

        $repository = $this->createStub(EmailTemplateRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria) => ['name' => 'account_validation'] === $criteria ? $emailTemplate : null
        );

        $renderer = $this->createStub(EmailTemplateRenderer::class);
        $renderer->method('renderBody')->willReturnCallback(
            static fn (EmailTemplate $template, array $variables) => $template === $emailTemplate ? '<p>' . ($variables['signed_url'] ?? '') . '</p>' : ''
        );

        $extension = new EmailTemplateExtension($repository, $renderer);

        $this->assertSame(
            '<p>https://example.test/verify</p>',
            $extension->renderEmailTemplateBody('account_validation', ['signed_url' => 'https://example.test/verify'])
        );
    }

    // A missing/renamed EmailTemplate must not break the email it's embedded into - only render nothing
    public function testRenderEmailTemplateBodyReturnsEmptyStringWhenNotFound(): void
    {
        $repository = $this->createConfiguredStub(EmailTemplateRepository::class, ['findOneBy' => null]);
        $renderer = $this->createStub(EmailTemplateRenderer::class);

        $extension = new EmailTemplateExtension($repository, $renderer);

        $this->assertSame('', $extension->renderEmailTemplateBody('does_not_exist'));
    }
}
