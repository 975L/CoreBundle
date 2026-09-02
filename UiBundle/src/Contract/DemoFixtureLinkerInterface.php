<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Implement beside DemoFixtureProviderInterface to add the rows that can only be written once the dataset has been flushed: an order naming the very items it holds, a statistic counting them. Everything yielded by the providers has an identifier by the time this is called, and what is yielded here is recorded and taken back exactly like the rest (see DemoFixtureRegistry, and the demo application's own loader).
//
// Only for what genuinely needs an identifier. A row referring to another by object reference rides Doctrine's own ordering and belongs in getDemoFixtures() with everything else - this second pass exists for the values a bundle copies rather than points at, the snapshot an order freezes being the reason it was written.
interface DemoFixtureLinkerInterface
{
    /**
     * Entities to persist once the first pass has been flushed, yielded in the order they must be flushed.
     *
     * A linker is free to read back what it seeded: the rows are in the database, and querying them by their own
     * natural key is steadier than remembering the objects between the two passes.
     *
     * @return iterable<object>
     */
    public function getLinkedDemoFixtures(): iterable;
}
