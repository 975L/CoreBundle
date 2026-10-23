<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

// A provider's credential read from its config, the one way every provider reads them: the class using it holds a $configService
trait OAuthCredentialTrait
{
    // A credential pasted from a console carries whatever whitespace came with it, and an empty string is not a value
    private function credential(string $slug): ?string
    {
        $value = $this->configService->get($slug);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' !== $value ? $value : null;
    }
}
