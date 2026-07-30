<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\FormActionInterface;

class FormActionRegistry
{
    /** @var array<string, FormActionInterface> */
    private array $actions = [];

    // Called once per tagged provider by FormActionProviderPass
    public function addProvider(FormActionInterface $action): void
    {
        $this->actions[$action->getKey()] = $action;
    }

    public function has(string $key): bool
    {
        return isset($this->actions[$key]);
    }

    /** @return string[] */
    public function getKeys(): array
    {
        return array_keys($this->actions);
    }

    public function get(string $key): FormActionInterface
    {
        return $this->actions[$key] ?? throw new \InvalidArgumentException(sprintf('No FormAction registered for key "%s"', $key));
    }
}
