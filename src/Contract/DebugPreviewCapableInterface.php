<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

/**
 * Optional capability a FormActionInterface provider can implement when its "success" can be silent from the visitor's point of view in debug mode (e.g. SendEmailFormAction, which renders instead of actually sending when ROLE_SUPER_ADMIN + "email-debug" - see c975L\UiBundle\Service\EmailService::send()) - FormController checks for this after calling handle() and shows the preview instead of the usual flash+redirect, so debug mode still gives visible feedback.
 */
interface DebugPreviewCapableInterface
{
    /**
     * Returns and clears whatever was stashed during the last handle() call, or null if there's nothing to show.
     */
    public function consumeDebugPreview(): ?string;
}
