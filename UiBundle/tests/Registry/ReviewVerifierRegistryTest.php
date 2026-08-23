<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\ReviewVerifierInterface;
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Registry\ReviewVerifierRegistry;
use PHPUnit\Framework\TestCase;

// Whether a review can be marked verified - the badge says the site checked, so anything it cannot establish is a no
class ReviewVerifierRegistryTest extends TestCase
{
    public function testNothingIsVerifiedOnASiteBringingNoVerifier(): void
    {
        $this->assertFalse(new ReviewVerifierRegistry()->verify($this->review()));
    }

    public function testAVerifierVouchingForTheAddressVerifiesTheReview(): void
    {
        $registry = new ReviewVerifierRegistry();
        $registry->addProvider($this->verifier('shop_product', true));

        $this->assertTrue($registry->verify($this->review()));
    }

    public function testAVerifierThatCannotEstablishItLeavesItUnverified(): void
    {
        $registry = new ReviewVerifierRegistry();
        $registry->addProvider($this->verifier('shop_product', true, supports: false));

        $this->assertFalse($registry->verify($this->review()));
    }

    // A review about the site itself, or one an anonymous import carries, has nothing to check an address against
    public function testAReviewWithNoOwnerOrNoAddressIsNeverVerified(): void
    {
        $registry = new ReviewVerifierRegistry();
        $registry->addProvider($this->verifier('shop_product', true));

        $this->assertFalse($registry->verify(new Review()->setAuthorEmail('marie@example.org')));
        $this->assertFalse($registry->verify(new Review()->setOwnerType('shop_product')->setOwnerId(12)));
    }

    // The address travels as it was typed; normalising it is the verifier's own business, which is where the orders are read
    public function testTheAddressIsHandedOverToTheVerifier(): void
    {
        $seen = null;
        $verifier = $this->createStub(ReviewVerifierInterface::class);
        $verifier->method('supports')->willReturn(true);
        $verifier->method('hasObtained')->willReturnCallback(function (string $ownerType, int $ownerId, string $email) use (&$seen): bool {
            $seen = [$ownerType, $ownerId, $email];

            return true;
        });

        $registry = new ReviewVerifierRegistry();
        $registry->addProvider($verifier);
        $registry->verify($this->review());

        $this->assertSame(['shop_product', 12, 'Marie@Example.org'], $seen);
    }

    // Fails loudly rather than letting one verifier silently win and the other become unreachable
    public function testTwoVerifiersOnOneOwnerTypeAreRefused(): void
    {
        $registry = new ReviewVerifierRegistry();
        $registry->addProvider($this->verifier('shop_product', true));
        $registry->addProvider($this->verifier('shop_product', false));

        $this->expectException(\LogicException::class);

        $registry->verify($this->review());
    }

    private function review(): Review
    {
        return new Review()
            ->setOwnerType('shop_product')
            ->setOwnerId(12)
            ->setAuthorEmail('Marie@Example.org')
        ;
    }

    private function verifier(string $ownerType, bool $obtained, bool $supports = true): ReviewVerifierInterface
    {
        $verifier = $this->createStub(ReviewVerifierInterface::class);
        $verifier->method('supports')->willReturnCallback(static fn (string $asked): bool => $supports && $asked === $ownerType);
        $verifier->method('hasObtained')->willReturn($obtained);

        return $verifier;
    }
}
