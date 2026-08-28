<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Implement to hand a demo site the dataset a bundle stands behind (see DemoFixtureRegistry): what a demo site persists to be browsed - this bundle shipping the contract and the registry, and nothing that writes. A bundle describes only the entities it owns, so a demo instance is seeded by the bundles it installs and by nothing else - which is what lets a demo be run per bundle. Sample data a block showcase renders without ever touching the database stays in BlockFixtureProviderInterface and GalleryShowcaseProviderInterface: this one is the persisted half, and a bundle offering both is expected to build its entities once and hand them to the two.
//
// Nothing says here what a reload empties: each row loaded is recorded as it is written (see the DemoFixture entity), and the next run takes back that and nothing else. A demo site keeps its own content - its pages, its menus, the showcase itself - in the very tables this dataset lands in, so a provider is never asked which tables may be emptied: none may.
interface DemoFixtureProviderInterface
{
    /**
     * Entities to persist, yielded in the order they must be flushed.
     *
     * A Media handed to VichUploader must carry a temporary copy of its file, never the bundle's own placeholder: the upload moves the file it is given, and the placeholder would be gone after the first load.
     *
     * Only what is yielded here is recorded, so only what is yielded here is taken back. Whatever rides an ORM cascade off one of these - a product's items, a category's photographs - leaves with it, VichUploader's removal listener firing on each and taking the uploaded files off the disk.
     *
     * @return iterable<object>
     */
    public function getDemoFixtures(): iterable;
}
