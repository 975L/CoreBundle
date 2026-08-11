<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Nelmio\SecurityBundle\EventListener\ContentSecurityPolicyListener;

// Gives the nonce bound to the response being built, for the code that has to write one outside a template (see BlockExtension::applyNonce). Interposed rather than injecting NelmioSecurityBundle's listener directly: that class is final, so anything depending on it cannot be doubled in a test
class CspNonceProvider
{
    // Null for a site that has configured no csp section at all: NelmioSecurityBundle only registers its listener alongside such a section, and the service is wired to take it optionally (see services.yaml) rather than let a whole container fail to compile over a header that site never asked for
    public function __construct(
        private ?ContentSecurityPolicyListener $listener = null,
    ) {
    }

    // Asking for it is also what puts it in the response's own style-src, so the element it lands on is authorized
    // Empty when there is no policy to satisfy, which is what tells a caller to write no nonce attribute at all (see BlockExtension::applyNonce)
    public function styleNonce(): string
    {
        return $this->listener?->getNonce('style') ?? '';
    }
}
