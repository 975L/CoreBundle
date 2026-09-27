<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Account;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Entity\Favorite;
use c975L\UiBundle\Entity\Rating;
use c975L\UiBundle\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;

// UiBundle's part of a member's export, written here as UiBundle knows nothing of accounts: the favorites and ratings held under the account's key ("u<id>", see FavoriteService and RatingService), and the reviews written on this site under its address
class ActivityAccountDataProvider implements AccountDataProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Under "favorites", "ratings" and "reviews", each left out when empty
    public function getAccountData(UserInterface $user): array
    {
        $key = 'u' . $user->getId();
        $email = method_exists($user, 'getEmail') ? (string) $user->getEmail() : '';

        return array_filter([
            'favorites' => array_map(static fn (Favorite $favorite): array => [
                'type' => $favorite->getOwnerType(),
                'id' => $favorite->getOwnerId(),
                'date' => $favorite->getCreatedAt(),
            ], $this->entityManager->getRepository(Favorite::class)->findBy(['holder' => $key])),
            'ratings' => array_map(static fn (Rating $rating): array => [
                'type' => $rating->getOwnerType(),
                'id' => $rating->getOwnerId(),
                'value' => $rating->getValue(),
                'date' => $rating->getCreatedAt(),
            ], $this->entityManager->getRepository(Rating::class)->findBy(['voter' => $key])),
            'reviews' => '' === $email ? [] : array_map(static fn (Review $review): array => [
                'type' => $review->getOwnerType(),
                'id' => $review->getOwnerId(),
                'name' => $review->getAuthorName(),
                'rating' => $review->getRating(),
                'comment' => $review->getComment(),
                'status' => $review->getStatus()->value,
                'date' => $review->getPublishedAt(),
            ], $this->entityManager->getRepository(Review::class)->findBy(['source' => Review::SOURCE_SITE, 'authorEmail' => $email])),
        ]);
    }
}
