<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Rating;
use c975L\UiBundle\Repository\RatingRepository;
use c975L\UiBundle\Service\RatingService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

// What one vote decides: who it counts as, what it is worth, and whether re-sending it means "again" or "no longer"
#[AllowMockObjectsWithoutExpectations]
class RatingServiceTest extends TestCase
{
    private const string TOKEN = '0123456789abcdef0123456789abcdef';

    // A scale is clamped exactly the way components/Progress/Rating.html.twig clamps the row it prints, both ends of it
    public function testTheScaleIsClampedToWhatTheWidgetCanPrint(): void
    {
        $service = $this->service();

        $this->assertSame(1, $service->getScale(0));
        $this->assertSame(1, $service->getScale(-3));
        $this->assertSame(10, $service->getScale(500));
        $this->assertSame(5, $service->getScale(5));
    }

    public function testTheScaleFallsBackToTheSiteSettingThenToFive(): void
    {
        $this->assertSame(3, $this->service(configs: ['ui-rating-scale' => '3'])->getScale());
        $this->assertSame(5, $this->service()->getScale());
    }

    // A stored icon nobody styles any more still leaves a clickable widget rather than a row of blank boxes
    public function testAnUnknownIconFallsBackToTheStar(): void
    {
        $this->assertSame('star', $this->service()->getIcon('unicorn'));
        $this->assertSame('heart', $this->service()->getIcon('heart'));
        $this->assertSame('thumbs-up', $this->service(configs: ['ui-rating-icon' => 'thumbs-up'])->getIcon());
    }

    // The account wins over anything the browser sent: that is what makes a logged-in visitor's vote follow them elsewhere, and what stops it being renewed by clearing storage
    public function testAnAuthenticatedVisitorIsKeyedOnTheirAccount(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getId')->willReturn(7);

        $this->assertSame('u7', $this->service(user: $user)->resolveVoter(self::TOKEN));
    }

    public function testAnAnonymousVisitorIsKeyedOnTheirOwnToken(): void
    {
        $this->assertSame(self::TOKEN, $this->service()->resolveVoter(self::TOKEN));
    }

    // No token, or one shaped like anything else, is refused rather than replaced by one made up here: a server-minted key would count one vote per request
    public function testAVoterWithoutAUsableTokenIsRefused(): void
    {
        $service = $this->service();

        $this->assertNull($service->resolveVoter(null));
        $this->assertNull($service->resolveVoter(''));
        $this->assertNull($service->resolveVoter('short'));
        $this->assertNull($service->resolveVoter(str_repeat('z', 32)));
    }

    public function testAFirstVoteIsWrittenAndCounted(): void
    {
        $repository = $this->repository(null, ['average' => 4.0, 'count' => 1]);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist');
        $manager->expects($this->never())->method('remove');
        $manager->expects($this->once())->method('flush');

        $answer = $this->service($repository, $manager)->vote('book', 42, 4, self::TOKEN);

        $this->assertSame(['value' => 4, 'average' => 4.0, 'count' => 1], $answer);
    }

    // Correcting a score stays one vote, which is the whole point of the unique constraint the row sits under
    public function testASecondVoteWithAnotherScoreUpdatesTheSameRow(): void
    {
        $existing = new Rating()->setOwnerType('book')->setOwnerId(42)->setVoter(self::TOKEN)->setValue(5);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('persist');
        $manager->expects($this->never())->method('remove');

        $answer = $this->service($this->repository($existing, ['average' => 3.0, 'count' => 1]), $manager)->vote('book', 42, 3, self::TOKEN);

        $this->assertSame(3, $existing->getValue());
        $this->assertSame(3, $answer['value']);
    }

    // Re-sending the score already stored takes the vote back - the toggle a single-icon "like" is made of, and how a longer scale is undone
    public function testVotingTheStoredScoreAgainRemovesTheVote(): void
    {
        $existing = new Rating()->setOwnerType('book')->setOwnerId(42)->setVoter(self::TOKEN)->setValue(1);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('remove')->with($existing);

        $answer = $this->service($this->repository($existing, ['average' => 0.0, 'count' => 0]), $manager, ['ui-rating-scale' => '1'])->vote('book', 42, 1, self::TOKEN);

        $this->assertNull($answer['value']);
    }

    // The value is clamped server-side too: what a template offers is not what a caller has to send
    public function testAValueOutsideTheScaleIsClampedBeforeBeingStored(): void
    {
        $repository = $this->repository(null, ['average' => 5.0, 'count' => 1]);
        $stored = null;
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('persist')->willReturnCallback(function (Rating $rating) use (&$stored): void {
            $stored = $rating->getValue();
        });

        $this->service($repository, $manager)->vote('book', 42, 99, self::TOKEN);

        $this->assertSame(5, $stored);
    }

    public function testAValueUnderOneIsClampedUpToTheFirstIcon(): void
    {
        $repository = $this->repository(null, ['average' => 1.0, 'count' => 1]);
        $stored = null;
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('persist')->willReturnCallback(function (Rating $rating) use (&$stored): void {
            $stored = $rating->getValue();
        });

        $this->service($repository, $manager)->vote('book', 42, 0, self::TOKEN);

        $this->assertSame(1, $stored);
    }

    // The scale is read from the site and from nowhere else: a caller sending its own would store a score above what the site is rated out of, and the public average would then read "7.3/5"
    public function testAVoteIsBoundedByTheSiteScaleWhateverTheCallerAsksFor(): void
    {
        $repository = $this->repository(null, ['average' => 3.0, 'count' => 1]);
        $stored = null;
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('persist')->willReturnCallback(function (Rating $rating) use (&$stored): void {
            $stored = $rating->getValue();
        });

        $this->service($repository, $manager, ['ui-rating-scale' => '3'])->vote('book', 42, 10, self::TOKEN);

        $this->assertSame(3, $stored);
    }

    // Two votes sent at once both read no row and both insert one: the second insert is a conflict the caller can be told about, not a server error
    public function testAConcurrentFirstVoteAnswersAConflict(): void
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('flush')->willThrowException($this->createMock(UniqueConstraintViolationException::class));

        $this->expectException(ConflictHttpException::class);

        $this->service($this->repository(null, ['average' => 4.0, 'count' => 1]), $manager)->vote('book', 42, 4, self::TOKEN);
    }

    // Every glyph the setting offers is one the stylesheet cuts out, and the other way round
    public function testTheIconsAreTheOnesTheStylesheetMasks(): void
    {
        $sass = (string) file_get_contents(__DIR__ . '/../../sass/_rating.scss');

        foreach (RatingService::ICONS as $icon) {
            $this->assertStringContainsString('"' . $icon . '":', $sass, sprintf('Icon "%s" is offered but the stylesheet masks nothing for it', $icon));
        }
    }

    private function repository(?Rating $existing, array $aggregate): RatingRepository
    {
        $repository = $this->createMock(RatingRepository::class);
        $repository->method('findOneByVoter')->willReturn($existing);
        $repository->method('getAggregate')->willReturn($aggregate);

        return $repository;
    }

    private function service(
        ?RatingRepository $repository = null,
        ?EntityManagerInterface $manager = null,
        array $configs = [],
        ?UserInterface $user = null,
    ): RatingService {
        $configService = $this->createMock(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnCallback(fn (string $key): bool => \array_key_exists($key, $configs));
        $configService->method('get')->willReturnCallback(fn (string $key): mixed => $configs[$key] ?? null);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        return new RatingService(
            $repository ?? $this->createMock(RatingRepository::class),
            $manager ?? $this->createMock(EntityManagerInterface::class),
            $configService,
            $security,
        );
    }
}
