<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

/**
 * Implement to feed a block showcase (see BlockFixtureRegistry) with sample data for the block kinds a bundle registers via the "ui.block" tag, letting satellite bundles show their own kinds there without UiBundle knowing about them.
 */
interface BlockFixtureProviderInterface
{
    /**
     * "data" has the same shape Block::setData() stores; a kind with a single unlabelled example can use '' as its only variant key.
     *
     * @return array<string, array<string, array<string, mixed>>> block kind => variant label => data
     */
    public function getFixtures(): array;
}
