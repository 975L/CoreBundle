<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Repository\RatingRepository;
use c975L\UiBundle\Service\RatingService;
use c975L\UiBundle\Twig\RatingExtension;
use c975L\UiBundle\Twig\RatingRuntime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class RatingRuntimeTest extends TestCase
{
    // Twig builds every Extension eagerly, on every request that merely boots the environment - the aggregate query has to hang off the runtime instead
    public function testTheExtensionCarriesNoDependencyOfItsOwn(): void
    {
        $this->assertSame([], new \ReflectionClass(RatingExtension::class)->getConstructor()?->getParameters() ?? []);

        $names = array_map(static fn (object $function): string => $function->getName(), new RatingExtension()->getFunctions());
        $this->assertSame(['ui_rating', 'ui_ratings'], $names);
    }

    // What the component draws itself from: the public tally, and the shape the site chose for it
    public function testARatingCarriesTheTallyAndTheShape(): void
    {
        $service = $this->createMock(RatingService::class);
        $service->method('getScale')->willReturn(5);
        $service->method('getIcon')->willReturn('heart');
        $service->method('getAggregate')->willReturn(['average' => 4.2, 'count' => 37]);

        $rating = new RatingRuntime($service, $this->createMock(RatingRepository::class))->rating('book', 42);

        $this->assertSame(
            ['ownerType' => 'book', 'ownerId' => 42, 'scale' => 5, 'icon' => 'heart', 'average' => 4.2, 'count' => 37],
            $rating
        );
    }

    // Never the visitor's own vote: the page carrying this is public and shared, so what one of them voted would be handed to the next
    public function testARatingSaysNothingAboutWhoIsReadingIt(): void
    {
        $service = $this->createMock(RatingService::class);
        $service->method('getScale')->willReturn(5);
        $service->method('getIcon')->willReturn('star');
        $service->method('getAggregate')->willReturn(['average' => 0.0, 'count' => 0]);

        $rating = new RatingRuntime($service, $this->createMock(RatingRepository::class))->rating('book', 42);

        $this->assertArrayNotHasKey('value', $rating);
        $this->assertArrayNotHasKey('voter', $rating);
    }

    // The tally a listing already read is taken as it is: thirty cards handed their own would otherwise run thirty queries behind the one the listing already ran
    public function testATallyHandedOverIsNotQueriedAgain(): void
    {
        $service = $this->createMock(RatingService::class);
        $service->method('getScale')->willReturn(5);
        $service->method('getIcon')->willReturn('star');
        $service->expects($this->never())->method('getAggregate');

        $rating = new RatingRuntime($service, $this->createMock(RatingRepository::class))
            ->rating('book', 42, null, null, ['average' => 4.5, 'count' => 2]);

        $this->assertSame(4.5, $rating['average']);
        $this->assertSame(2, $rating['count']);
    }

    // A card of a catalog is no place to raise an error: an id nobody voted on, handed over as nothing at all, reads as no vote
    public function testATallyHandedOverEmptyReadsAsNoVote(): void
    {
        $service = $this->createMock(RatingService::class);
        $service->method('getScale')->willReturn(5);
        $service->method('getIcon')->willReturn('star');

        $rating = new RatingRuntime($service, $this->createMock(RatingRepository::class))
            ->rating('book', 42, null, null, []);

        $this->assertSame(0.0, $rating['average']);
        $this->assertSame(0, $rating['count']);
    }

    // A catalog page prints one row of stars per card, and asks for all of them at once
    public function testAListIsAskedForInOneGoAndZeroesTheOnesNobodyVotedOn(): void
    {
        $repository = $this->createMock(RatingRepository::class);
        $repository->expects($this->once())
            ->method('getAggregates')
            ->with('book', [1, 2, 3])
            ->willReturn([2 => ['average' => 4.5, 'count' => 2]])
        ;

        $ratings = new RatingRuntime($this->createMock(RatingService::class), $repository)->ratings('book', [1, 2, 3]);

        $this->assertSame(
            [2 => ['average' => 4.5, 'count' => 2], 1 => ['average' => 0.0, 'count' => 0], 3 => ['average' => 0.0, 'count' => 0]],
            $ratings
        );
    }
}
