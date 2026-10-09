<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

// Where a content stands on the networks (see SocialContentStatusProviderInterface): reserved by a post still to go out, at its planned moment, or published, at the moment it went out
final class SocialContentStatus
{
    public const string RESERVED = 'reserved';

    public const string PUBLISHED = 'published';

    public function __construct(
        public readonly string $state,
        public readonly \DateTimeImmutable $at,
    ) {
    }

    public function isPublished(): bool
    {
        return self::PUBLISHED === $this->state;
    }
}
