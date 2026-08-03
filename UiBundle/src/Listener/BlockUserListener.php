<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Listener;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

// Tracks who last touched a Block/Media, not who originally created it - $user is overwritten on every save (see preUpdate below), so it always reflects the last editor
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class BlockUserListener
{
    public function __construct(private Security $security)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (null !== $user = $this->currentUser($entity)) {
            $entity->setUser($user);
        }
    }

    // Doctrine already computed the update changeset by the time preUpdate fires, so a plain setter call here would be silently dropped from the SQL UPDATE. PreUpdateEventArgs::setNewValue() looks like the lighter fix but only works for a field already present in the changeset (it throws otherwise - see assertValidField() in Doctrine's own source) - "user" usually ISN'T there yet on an update that only touches other fields, which is exactly the common case here. recomputeSingleEntityChangeSet() re-diffs the whole entity so Doctrine picks up "user" even when it's the only field that changed.
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (null === $user = $this->currentUser($entity)) {
            return;
        }

        $entity->setUser($user);

        $entityManager = $args->getObjectManager();
        $entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet(
            $entityManager->getClassMetadata($entity::class),
            $entity
        );
    }

    // The currently logged-in user, if $entity is a Block/Media and someone is actually logged in
    private function currentUser(object $entity): ?UserInterface
    {
        if (!($entity instanceof Block || $entity instanceof Media)) {
            return null;
        }

        $user = $this->security->getUser();

        return $user instanceof UserInterface ? $user : null;
    }
}
