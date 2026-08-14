<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\CacheInvalidatorInterface;

class CacheInvalidatorRegistry
{
    /** @var CacheInvalidatorInterface[] */
    private array $invalidators = [];

    // Called once per discovered implementation by CacheInvalidatorPass
    public function addProvider(CacheInvalidatorInterface $invalidator): void
    {
        $this->invalidators[] = $invalidator;
    }

    // One failing invalidator must not keep the others from running, nor bring down the dashboard action that asked: what the editor wanted is every cache emptied, and a pool that was already unreachable is not made worse by saying so at the end
    public function invalidateAll(): void
    {
        $errors = [];
        foreach ($this->invalidators as $invalidator) {
            try {
                $invalidator->invalidate();
            } catch (\Throwable $exception) {
                $errors[] = $invalidator::class . ': ' . $exception->getMessage();
            }
        }

        if ([] !== $errors) {
            throw new \RuntimeException('Some caches could not be cleared - ' . implode(' | ', $errors));
        }
    }
}
