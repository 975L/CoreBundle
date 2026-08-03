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
 * Implement to backfill whatever a "form"-kind Block needs to work once it lands from another environment - a Page content export carries the Blocks alone, never the Form/EmailTemplate they point at, so an import has to seed them. Consumed by BlockDataImporter, which asks every registered provider in turn; a bundle owning none of the imported form names simply does nothing.
 */
interface FormBlockDependencyProviderInterface
{
    /**
     * @param array $blockData one {kind, data} block array, as found in a content export
     */
    public function ensureFormBlockDependenciesExist(array $blockData): void;
}
