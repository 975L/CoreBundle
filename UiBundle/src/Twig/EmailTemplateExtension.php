<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Repository\EmailTemplateRepository;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Twig\Attribute\AsTwigFunction;

// Embeds a named EmailTemplate's compiled body inside an app's own email layout
class EmailTemplateExtension
{
    public function __construct(
        private readonly EmailTemplateRepository $emailTemplateRepository,
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
    ) {
    }

    // Renders nothing when unknown: a renamed template must leave a section blank, never break the email
    #[AsTwigFunction('email_template_body', isSafe: ['html'])]
    public function renderEmailTemplateBody(string $name, array $variables = []): string
    {
        $emailTemplate = $this->emailTemplateRepository->findOneBy(['name' => $name]);

        return null !== $emailTemplate ? $this->emailTemplateRenderer->renderBody($emailTemplate, $variables) : '';
    }
}
