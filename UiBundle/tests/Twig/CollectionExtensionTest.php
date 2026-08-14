<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Twig\CollectionExtension;
use c975L\UiBundle\Twig\CollectionRuntime;
use PHPUnit\Framework\TestCase;

class CollectionExtensionTest extends TestCase
{
    // The actual rendering logic lives in CollectionRuntime (see CollectionRuntimeTest) - this extension only declares the functions, pointing Twig at the runtime's methods so it stays uninstantiated until a template actually calls one of them
    public function testGetFunctionsRegistersCollectionFunctionPointingAtRuntime(): void
    {
        $functions = new CollectionExtension()->getFunctions();
        $names = array_map(fn ($f) => $f->getName(), $functions);

        $this->assertSame(['collection_render_items', 'collection_render_entry'], $names);
        $this->assertSame([CollectionRuntime::class, 'renderItems'], $functions[0]->getCallable());
        $this->assertSame([CollectionRuntime::class, 'renderEntry'], $functions[1]->getCallable());
    }
}
