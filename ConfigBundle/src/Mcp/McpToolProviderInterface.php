<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Mcp;

// What a bundle hands the site's MCP endpoint: the key an agent token names it by and its tools, run with the rights of the token's account, collected by TaggedInterfacePass
interface McpToolProviderInterface
{
    // Key of the bundle as an agent token names it ("pilot", "book")
    public function getMcpKey(): string;

    // A sentence telling the agent how the tools go together, empty for none
    public function getMcpInstructions(): string;

    /** @return iterable<McpTool> */
    public function getMcpTools(): iterable;
}
