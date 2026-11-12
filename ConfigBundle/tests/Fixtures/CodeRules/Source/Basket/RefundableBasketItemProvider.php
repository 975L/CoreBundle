<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Basket;

use c975L\ConfigBundle\Tests\Fixtures\CodeRules\Source\Contract\RefundableInterface;

// Good: reads the test mode and takes back at refund what it delivered
class RefundableBasketItemProvider implements RefundableInterface
{
    public array $delivered = [];

    public function onBasketPaid(object $basket, array $itemsOfThisKind, array $checkoutData): void
    {
        if (!$basket->isTestMode()) {
            $this->delivered = $itemsOfThisKind;
        }
    }

    public function onBasketRefunded(object $basket, array $itemsOfThisKind): void
    {
        $this->delivered = [];
    }
}
