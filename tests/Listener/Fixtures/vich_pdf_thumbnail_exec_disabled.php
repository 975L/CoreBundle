<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// Namespace-scoped override: PHP resolves an unqualified function_exists() call made from
// c975L\UiBundle\Listener against this definition before falling back to the global one,
// letting the test simulate a managed host with exec() disabled without touching the real ini.
namespace c975L\UiBundle\Listener;

function function_exists(string $function): bool
{
    return 'exec' !== $function && \function_exists($function);
}
