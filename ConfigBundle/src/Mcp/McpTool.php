<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Mcp;

// One tool of a bundle: its name, what it does, the callable answering it (its parameters and their PHPDoc become the tool's schema), and whether it writes or deletes
final readonly class McpTool
{
    public function __construct(
        public string $name,
        public string $title,
        public string $description,
        public \Closure $handler,
        public bool $writes = false,
        public bool $destroys = false,
    ) {
    }
}
