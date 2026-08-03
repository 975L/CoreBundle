<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

// Stand-in form class name for BlockRegistryTest, never instantiated - BlockRegistry only stores the FQCN string. Its own file (not inlined in the test class) - src/Tests classes are autoloadable by consuming apps, whose attribute route loader recursively reflects every class under the bundle root, and PSR-4 requires one file per class for that to work.
class ArticleFormStub
{
}
