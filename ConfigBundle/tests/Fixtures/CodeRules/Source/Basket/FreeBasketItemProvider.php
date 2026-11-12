<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Basket;

// Good: an empty onBasketPaid() delivers nothing
class FreeBasketItemProvider
{
    public function onBasketPaid(object $basket, array $itemsOfThisKind, array $checkoutData): void
    {
    }
}
