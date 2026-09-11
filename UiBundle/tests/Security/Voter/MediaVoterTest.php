<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Security\Voter;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Security\Voter\MediaVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

class MediaVoterTest extends TestCase
{
    private function createMedia(bool $membersOnly): Media
    {
        return new Media()->setFilename('medias/site/tree.pdf')->setMembersOnly($membersOnly);
    }

    private function createMemberToken(): TokenInterface
    {
        $user = new InMemoryUser('member', null, ['ROLE_USER']);

        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    public function testAPublicMediaIsOpenToAnAnonymousVisitor(): void
    {
        $this->assertSame(VoterInterface::ACCESS_GRANTED, new MediaVoter()->vote(new NullToken(), $this->createMedia(false), [MediaVoter::VIEW]));
    }

    public function testAMediaReservedToMembersIsRefusedToAnAnonymousVisitor(): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, new MediaVoter()->vote(new NullToken(), $this->createMedia(true), [MediaVoter::VIEW]));
    }

    // Any signed-in visitor, whatever their role: no role is asked on purpose
    public function testAMediaReservedToMembersIsOpenToASignedInVisitor(): void
    {
        $this->assertSame(VoterInterface::ACCESS_GRANTED, new MediaVoter()->vote($this->createMemberToken(), $this->createMedia(true), [MediaVoter::VIEW]));
    }

    public function testAnyOtherAttributeOrSubjectIsNotItsBusiness(): void
    {
        $voter = new MediaVoter();

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), $this->createMedia(true), ['ROLE_ADMIN']));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), new \stdClass(), [MediaVoter::VIEW]));
    }
}
