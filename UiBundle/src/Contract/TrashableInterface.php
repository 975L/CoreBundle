<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// An entity whose back-office delete only marks it, its row and its files staying where they are until a second, deliberate deletion (see Readme). Method names match SiteBundle's Page, which has carried the same flag by hand since before this contract existed, so it can adopt TrashableTrait without renaming anything.
interface TrashableInterface
{
    public function isDeleted(): bool;

    public function setIsDeleted(bool $isDeleted): self;
}
