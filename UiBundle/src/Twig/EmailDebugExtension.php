<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Service\EmailService;
use Twig\Attribute\AsTwigFunction;

// Hands the Email:DebugPreview component what EmailService stashed instead of sending, the component being an anonymous one with no class of its own to inject the service into
class EmailDebugExtension
{
    public function __construct(
        // Stores EmailService.
        private readonly EmailService $emailService,
    ) {
    }

    /**
     * Returns and clears the previews of the emails debug mode held back, newest send last.
     *
     * @return string[]
     */
    #[AsTwigFunction('ui_email_debug_previews')]
    public function previews(): array
    {
        return $this->emailService->consumeDebugPreviews();
    }
}
