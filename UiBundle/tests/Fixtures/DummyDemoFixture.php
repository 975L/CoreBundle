<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Fixtures;

// An entity as far as this is concerned: something with an identifier, once the loader has flushed it
class DummyDemoFixture
{
    public function __construct(private readonly ?int $id)
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
