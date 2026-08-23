<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\ReviewVerifierInterface;
use c975L\UiBundle\DependencyInjection\Compiler\ReviewVerifierPass;
use c975L\UiBundle\Registry\ReviewVerifierRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FakeReviewVerifier implements ReviewVerifierInterface
{
    public function supports(string $ownerType): bool
    {
        return false;
    }

    public function hasObtained(string $ownerType, int $ownerId, string $email): bool
    {
        return false;
    }
}

class ReviewVerifierPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new ReviewVerifierPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements ReviewVerifierInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryReviewVerifierImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(ReviewVerifierRegistry::class);
        $container->register('shop.order_review_verifier', FakeReviewVerifier::class);
        $container->register('unrelated.service', \stdClass::class);

        new ReviewVerifierPass()->process($container);

        $calls = $container->getDefinition(ReviewVerifierRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('shop.order_review_verifier'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(ReviewVerifierRegistry::class);
        $container->register('broken.service', 'This\Class\Does\Not\Exist');

        new ReviewVerifierPass()->process($container);

        $this->assertSame([], $container->getDefinition(ReviewVerifierRegistry::class)->getMethodCalls());
    }
}
