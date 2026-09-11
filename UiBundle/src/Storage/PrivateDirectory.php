<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Storage;

use c975L\UiBundle\Contract\VichPrivateFileInterface;
use c975L\UiBundle\Entity\Media;

// Where an uploaded file lives when the web server must not hand it out: a whole entity class may be private (VichPrivateFileInterface, ShopBundle's paid downloads), a single Media row may be reserved to members (Media::isMembersOnly()). Every place moving, deleting or thumbnailing a file asks here, so the two ways of being private never drift apart
final class PrivateDirectory
{
    // The project-relative directory holding the entity's file, null when it sits under public/
    public static function resolve(object $entity): ?string
    {
        if ($entity instanceof VichPrivateFileInterface) {
            return $entity->getPrivateDirectory();
        }

        return $entity instanceof Media && $entity->isMembersOnly() ? Media::MEMBERS_ONLY_DIRECTORY : null;
    }
}
