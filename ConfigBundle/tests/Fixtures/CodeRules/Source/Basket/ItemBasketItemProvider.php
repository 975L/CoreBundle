<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Basket;

// Bad twice: delivers without reading the test mode, and without a refund hook
class ItemBasketItemProvider
{
    public array $delivered = [];

    public function onBasketPaid(object $basket, array $itemsOfThisKind, array $checkoutData): void
    {
        // A basket's isTestMode() named in a comment is no reading
        $this->delivered = $itemsOfThisKind;
    }
}
