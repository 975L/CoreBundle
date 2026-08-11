<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Wraps an EmailTemplateRenderer body in the app's own branded layout, so preview and real send match.
interface EmailLayoutProviderInterface
{
    /**
     * @param string $bodyHtml the already-rendered, email-safe body
     *
     * @return string the same body wrapped in the app's layout, ready to send
     */
    public function wrap(string $bodyHtml): string;
}
