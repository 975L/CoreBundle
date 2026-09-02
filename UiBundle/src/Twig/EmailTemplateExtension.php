<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Service\EmailTemplateRenderer;
use Twig\Attribute\AsTwigFunction;

// Embeds a named EmailTemplate's compiled body inside an app's own email layout
class EmailTemplateExtension
{
    public function __construct(
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
    ) {
    }

    // Renders nothing when unknown: a renamed template must leave a section blank, never break the email
    // The recipient's language decides which version is read, the site's own standing in where that language has none (see EmailTemplateRenderer::renderNamedBody) - this used to take whichever row the name matched first, which on a multilingual site was whichever locale happened to be created first
    #[AsTwigFunction('email_template_body', isSafe: ['html'])]
    public function renderEmailTemplateBody(string $name, array $variables = [], ?string $locale = null): string
    {
        return $this->emailTemplateRenderer->renderNamedBody($name, $variables, $locale) ?? '';
    }
}
