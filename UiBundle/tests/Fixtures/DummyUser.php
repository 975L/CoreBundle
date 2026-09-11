<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Fixtures;

use c975L\ConfigBundle\Contract\UserInterface;

// Stands in for App\Entity\User (app-space, not autoloadable from this bundle checkout) where a test needs a real class to wrap in a lazy ghost, which a PHPUnit stub is not - see CachedEntitySerializationTest. It holds a property on purpose: PHP creates the ghost of a property-less class already initialized
class DummyUser implements UserInterface
{
    private string $email = 'user@example.test';

    public function getId(): ?int
    {
        return 1;
    }

    public function getRoles(): array
    {
        return [];
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
