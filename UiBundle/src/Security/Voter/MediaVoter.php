<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Security\Voter;

use c975L\UiBundle\Entity\Media;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

// Decides who may open a media's file through MediaController: anybody for a public one, any signed-in visitor for one reserved to members. No role is asked on purpose - a family site hands one shared account around, and narrowing it to a role is left for the day a site needs it
/** @extends Voter<string, Media> */
class MediaVoter extends Voter
{
    public const string VIEW = 'C975L_VIEW_MEDIA';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof Media;
    }

    // An anonymous visitor carries a token too, one holding no user
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return !$subject->isMembersOnly() || null !== $token->getUser();
    }
}
