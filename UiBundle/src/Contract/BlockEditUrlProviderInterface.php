<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use c975L\UiBundle\Entity\Block;

// Lets any bundle owning blocks (e.g. SiteBundle's Page) resolve the EasyAdmin edit URL of a given Block's owning entity, for the front-end "Edit this block" hover button - see BlockEditUrlProviderPass.
interface BlockEditUrlProviderInterface
{
    /**
     * @param Block[] $blocks the Block rows to resolve, already loaded by the caller
     *
     * @return array<int, string> edit URLs keyed by Block id, only for blocks this provider recognizes as its own
     */
    public function getEditUrls(array $blocks): array;
}
