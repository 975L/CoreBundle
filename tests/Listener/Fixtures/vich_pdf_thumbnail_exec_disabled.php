<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// Namespace-scoped override of function_exists(), simulating a host with exec() disabled
namespace c975L\UiBundle\Listener;

function function_exists(string $function): bool
{
    return 'exec' !== $function && \function_exists($function);
}
