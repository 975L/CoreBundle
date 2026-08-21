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
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Rating;
use c975L\UiBundle\Repository\RatingRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

// Everything a vote decides, kept out of RatingController so the controller only ever deals with http
class RatingService
{
    // The glyphs a site picks its rating icon from. A closed list on purpose: each one is a mask in sass/_rating.scss (a modifier class, not an inline style, the sites running this bundle serving a CSP with no unsafe-inline), so an icon nobody styled would paint nothing at all. ConfigsJsonTest checks the "ui-rating-icon" choices still say exactly this
    public const array ICONS = ['star', 'heart', 'thumbs-up', 'face-smile'];

    public const string DEFAULT_ICON = 'star';

    // Ten is what components/Progress/Rating.html.twig prints at most, a longer row not fitting a phone; one is the "like" end of the same widget, where the average gives way to a count
    public const int MIN_SCALE = 1;

    public const int MAX_SCALE = 10;

    public const int DEFAULT_SCALE = 5;

    // What a browser-held token has to look like to be accepted (see resolveVoter()) - 32 hex characters, the shape assets/js/rating.js generates
    private const string TOKEN_PATTERN = '/^[0-9a-f]{32}$/';

    public function __construct(
        private readonly RatingRepository $ratingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigServiceInterface $configService,
        private readonly Security $security,
    ) {
    }

    // How many icons the widget shows, the site's own setting unless the calling template asked for another one - a catalog of books rated out of five can still carry a plain "like" elsewhere on the same site
    public function getScale(?int $scale = null): int
    {
        $scale ??= $this->readConfigInt('ui-rating-scale', self::DEFAULT_SCALE);

        return max(self::MIN_SCALE, min(self::MAX_SCALE, $scale));
    }

    // Falls back to the star rather than to nothing: a site whose stored icon no longer exists still gets a widget it can click
    public function getIcon(?string $icon = null): string
    {
        $icon ??= $this->readConfigString('ui-rating-icon', self::DEFAULT_ICON);

        return \in_array($icon, self::ICONS, true) ? $icon : self::DEFAULT_ICON;
    }

    /**
     * @return array{average: float, count: int}
     */
    public function getAggregate(string $ownerType, int $ownerId): array
    {
        return $this->ratingRepository->getAggregate($ownerType, $ownerId);
    }

    /**
     * Who is voting, as the single opaque key Rating::$voter stores.
     *
     * An authenticated visitor is keyed on the account, so the vote follows them to another browser and cannot be renewed by clearing anything. Anyone else is keyed on a token their own browser made up and keeps: best-effort by construction - emptying the browser's storage buys another vote - but it needs no cookie banner, being neither read nor written before the visitor asks to vote, and no address of theirs is stored anywhere.
     *
     * Returns null when an anonymous caller sent no usable token, the vote then being refused rather than attributed to a key the server invented.
     */
    public function resolveVoter(?string $token): ?string
    {
        $user = $this->security->getUser();
        if ($user instanceof UserInterface && null !== $user->getId()) {
            return 'u' . $user->getId();
        }

        return null !== $token && 1 === preg_match(self::TOKEN_PATTERN, $token) ? $token : null;
    }

    /**
     * Records a vote and hands back what the widget has to show afterwards.
     *
     * Re-sending the value already stored removes it instead of storing it twice: that is the toggle a "like" needs (a scale of 1, where clicking the single heart again means "no longer"), and on a longer scale it is how a visitor takes their score back. Any other value updates the row, so correcting a 5 into a 3 stays one vote.
     *
     * The score is bounded by the site's own scale and by nothing the caller sends: a template showing more icons than the site is rated out of still displays them (see getScale()), but what gets stored never goes above the setting.
     *
     * @return array{value: int|null, average: float, count: int} value is the voter's own score once written, null once removed
     */
    public function vote(string $ownerType, int $ownerId, int $value, string $voter): array
    {
        $value = max(1, min($this->getScale(), $value));

        $rating = $this->ratingRepository->findOneByVoter($ownerType, $ownerId, $voter);

        if (null === $rating) {
            $rating = new Rating()
                ->setOwnerType($ownerType)
                ->setOwnerId($ownerId)
                ->setVoter($voter)
                ->setValue($value)
            ;
            $this->entityManager->persist($rating);
            $own = $value;
        } elseif ($rating->getValue() === $value) {
            $this->entityManager->remove($rating);
            $own = null;
        } else {
            $rating->setValue($value);
            $own = $value;
        }

        // Two votes sent at once (a double-click, two tabs) both read no row and both insert one: the second flush hits uniq_rating_owner_voter, and a 409 says what happened where an uncaught exception would answer 500. No retry - the entity manager is closed once a flush failed, nothing can be read back through it
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException();
        }

        return ['value' => $own] + $this->ratingRepository->getAggregate($ownerType, $ownerId);
    }

    private function readConfigString(string $key, string $default): string
    {
        if (!$this->configService->hasParameter($key)) {
            return $default;
        }

        $value = $this->configService->get($key);

        return \is_string($value) && '' !== $value ? $value : $default;
    }

    private function readConfigInt(string $key, int $default): int
    {
        if (!$this->configService->hasParameter($key)) {
            return $default;
        }

        $value = $this->configService->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }
}
