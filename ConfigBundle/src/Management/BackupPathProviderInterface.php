<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// Implemented by any bundle holding files no git clone and no database dump can bring back: ShopBundle its invoices, a gallery its originals. The backup used to archive public/ and private/ whole, which made it this bundle's business to know where every other one stores things, and forced a site with an unusual layout to be backed up wrongly rather than differently
interface BackupPathProviderInterface
{
    /** @return BackupPath[] paths relative to the project directory, the ones that don't exist being skipped */
    public function getBackupPaths(): array;
}
