<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\ReviewReplyPublisherInterface;
use c975L\UiBundle\DependencyInjection\Compiler\ReviewReplyPublisherPass;
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Registry\ReviewReplyRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FakeReviewReplyPublisher implements ReviewReplyPublisherInterface
{
    public function supports(Review $review): bool
    {
        return false;
    }

    public function publish(Review $review): void
    {
    }
}

class ReviewReplyPublisherPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new ReviewReplyPublisherPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements ReviewReplyPublisherInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryReviewReplyPublisherImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(ReviewReplyRegistry::class);
        $container->register('social.google_review_reply_publisher', FakeReviewReplyPublisher::class);
        $container->register('unrelated.service', \stdClass::class);

        new ReviewReplyPublisherPass()->process($container);

        $calls = $container->getDefinition(ReviewReplyRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('social.google_review_reply_publisher'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(ReviewReplyRegistry::class);
        $container->register('broken.service', 'This\Class\Does\Not\Exist');

        new ReviewReplyPublisherPass()->process($container);

        $this->assertSame([], $container->getDefinition(ReviewReplyRegistry::class)->getMethodCalls());
    }
}
