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
    // The actual rendering logic lives in CollectionRuntime (see CollectionRuntimeTest) - this extension only declares the function, pointing Twig at the runtime's method so it stays uninstantiated until a template actually calls collection_render_items()
    public function testGetFunctionsRegistersCollectionFunctionPointingAtRuntime(): void
    {
        $functions = (new CollectionExtension())->getFunctions();
        $names = array_map(fn ($f) => $f->getName(), $functions);

        $this->assertSame(['collection_render_items'], $names);
        $this->assertSame([CollectionRuntime::class, 'renderItems'], $functions[0]->getCallable());
    }
}
