<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Account;

use App\Entity\User;
use c975L\ConfigBundle\Account\AccountDataCollector;
use c975L\ConfigBundle\Account\AccountDataProviderInterface;
use c975L\ConfigBundle\Account\ActivityAccountDataProvider;
use c975L\ConfigBundle\Account\ProfileAccountDataProvider;
use c975L\ConfigBundle\Tests\Fixtures\UserStub;
use c975L\UiBundle\Entity\Favorite;
use c975L\UiBundle\Entity\Rating;
use c975L\UiBundle\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;

class AccountDataCollectorTest extends TestCase
{
    // Every provider's part in one export, dates written ISO 8601 however deep they sit
    public function testMergesThePartsAndWritesTheDates(): void
    {
        $orders = $this->createStub(AccountDataProviderInterface::class);
        $orders->method('getAccountData')->willReturn(['orders' => [['date' => new \DateTimeImmutable('2026-09-27 10:00:00+00:00')]]]);
        $nothing = $this->createStub(AccountDataProviderInterface::class);
        $nothing->method('getAccountData')->willReturn([]);

        $data = new AccountDataCollector([$orders, $nothing])->collect(new UserStub());

        $this->assertSame(['orders' => [['date' => '2026-09-27T10:00:00+00:00']]], $data);
    }

    // The favorites and ratings held under the account's key, and the reviews written here under its address
    public function testTheActivityIsReadUnderTheAccount(): void
    {
        $favorite = new Favorite()->setOwnerType('book')->setOwnerId(3)->setHolder('u42');
        $rating = new Rating()->setOwnerType('book')->setOwnerId(3)->setVoter('u42')->setValue(5);

        $queries = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(function (string $class) use ($favorite, $rating, &$queries): EntityRepository {
            $repository = $this->createStub(EntityRepository::class);
            $repository->method('findBy')->willReturnCallback(static function (array $criteria) use ($class, $favorite, $rating, &$queries): array {
                $queries[$class] = $criteria;

                return ['Favorite' => [$favorite], 'Rating' => [$rating]][new \ReflectionClass($class)->getShortName()] ?? [];
            });

            return $repository;
        });

        $data = new ActivityAccountDataProvider($entityManager)->getAccountData(new UserStub('user@example.test')->withId(42));

        $this->assertSame(['favorites', 'ratings'], array_keys($data));
        $this->assertSame(5, $data['ratings'][0]['value']);
        $this->assertSame(['holder' => 'u42'], $queries[Favorite::class]);
        $this->assertSame(['source' => Review::SOURCE_SITE, 'authorEmail' => 'user@example.test'], $queries[Review::class]);
    }

    // The profile is every column of the site's User but the password hash
    public function testTheProfileLeavesThePasswordOut(): void
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getFieldNames')->willReturn(['id', 'email', 'password', 'firstname']);
        $metadata->method('getFieldValue')->willReturnCallback(static fn (object $user, string $field): string => $field . '-value');
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        $data = new ProfileAccountDataProvider($entityManager)->getAccountData(new User());

        $this->assertSame(['profile' => ['id' => 'id-value', 'email' => 'email-value', 'firstname' => 'firstname-value']], $data);
    }
}
