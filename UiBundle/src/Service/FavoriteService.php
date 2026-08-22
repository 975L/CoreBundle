<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\UiBundle\Entity\Favorite;
use c975L\UiBundle\Model\CollectionItem;
use c975L\UiBundle\Registry\FavoriteItemRegistry;
use c975L\UiBundle\Repository\FavoriteRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

// Everything a wishlist decides, kept out of FavoriteController so the controller only ever deals with http - same split as RatingService, whose voter key this one holds its list by
class FavoriteService
{
    // What a browser-held token has to look like to be accepted, and the shape assets/js/favorite.js generates - the same 32 hex characters a vote is cast under
    private const string TOKEN_PATTERN = '/^[0-9a-f]{32}$/';

    public function __construct(
        private readonly FavoriteRepository $favoriteRepository,
        private readonly FavoriteItemRegistry $favoriteItemRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    /**
     * Whose list this is, as the single opaque key Favorite::$holder stores.
     *
     * An authenticated visitor is keyed on the account, so their list follows them to another browser - which is the whole of what "finding your list back by signing in" means here. Anyone else is keyed on a token their own browser made up and keeps: emptying that storage loses the list, and it needs no cookie banner, being neither read nor written before the visitor puts something aside.
     *
     * Returns null when an anonymous caller sent no usable token, the entry then being refused rather than filed under a key the server invented.
     */
    public function resolveHolder(?string $token): ?string
    {
        $user = $this->security->getUser();
        if ($user instanceof UserInterface && null !== $user->getId()) {
            return 'u' . $user->getId();
        }

        return null !== $token && 1 === preg_match(self::TOKEN_PATTERN, $token) ? $token : null;
    }

    /**
     * Hands the list a browser was holding over to the account that just signed in, and answers with the key to use from now on.
     *
     * Called on every authenticated request that carries a token rather than from a login listener: the token lives in the visitor's own storage and no login event ever sees it. Doing it here also absorbs the case a listener would miss - a list started anonymously in one tab while already signed in in another.
     */
    public function merge(?string $token): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof UserInterface || null === $user->getId()) {
            return;
        }

        if (null === $token || 1 !== preg_match(self::TOKEN_PATTERN, $token)) {
            return;
        }

        $this->favoriteRepository->moveHolder($token, 'u' . $user->getId());
    }

    /**
     * Puts a thing aside, or takes it back out - the same click both ways, which is what a heart is.
     *
     * @return array{favorited: bool, count: int} what the button repaints itself from: the server decides, the browser never assumes
     */
    public function toggle(string $ownerType, int $ownerId, string $holder): array
    {
        $favorite = $this->favoriteRepository->findOneByHolder($ownerType, $ownerId, $holder);

        if (null === $favorite) {
            $this->entityManager->persist(new Favorite()
                ->setOwnerType($ownerType)
                ->setOwnerId($ownerId)
                ->setHolder($holder));
            $favorited = true;
        } else {
            $this->entityManager->remove($favorite);
            $favorited = false;
        }

        // Two clicks at once (a double-click, two tabs) both read no row and both insert one: the second flush hits uniq_favorite_owner_holder, and a 409 says what happened where an uncaught exception would answer 500. No retry - the entity manager is closed once a flush failed
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException();
        }

        // What the drawer will show, not what the table holds: an item favorited then unpublished no longer resolves, and a badge disagreeing with the list it opens is read as a bug
        return ['favorited' => $favorited, 'count' => \count($this->list($holder))];
    }

    /**
     * The whole list, drawn in the order it was built, newest first.
     *
     * @return list<array{ownerType: string, ownerId: int, item: CollectionItem}>
     */
    public function list(string $holder): array
    {
        return $this->favoriteItemRegistry->resolve($this->favoriteRepository->findIdsByHolder($holder));
    }

    // The keys the buttons of the site paint themselves from ("shop_product:39"), so a visitor arriving on a new device sees their own hearts filled once this list has been read
    public function keys(string $holder): array
    {
        $keys = [];

        foreach ($this->favoriteRepository->findIdsByHolder($holder) as $ownerType => $ownerIds) {
            foreach ($ownerIds as $ownerId) {
                $keys[] = $ownerType . ':' . $ownerId;
            }
        }

        return $keys;
    }

    public function count(string $holder): int
    {
        return $this->favoriteRepository->countForHolder($holder);
    }
}
