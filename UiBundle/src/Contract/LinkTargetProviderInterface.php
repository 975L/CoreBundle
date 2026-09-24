<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// What a block's link field suggests besides the address typed by hand: the pages of the site and their sections, as the menus offer them. Implemented by the bundle owning those pages, which also turns the value picked back into a url when the block is rendered (see InternalLinkLocalizerInterface) - with none registered the list is empty, and the field only takes the address typed into it
interface LinkTargetProviderInterface
{
    /** @return array<string, string> label => value stored in the field, e.g. "Services → Our offer" => "page:12#offer-34" */
    public function linkTargets(): array;
}
