<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Entity\AppToken;
use c975L\ConfigBundle\Repository\AppTokenRepository;
use c975L\ConfigBundle\Service\AppTokenManager;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

// A code is traded once, in time, and only by whoever holds the verifier its challenge was made from
class AppTokenManagerTest extends TestCase
{
    private const string VERIFIER = 'a-verifier-only-the-app-holds-0123456789-abcdef';

    /** @var list<AppToken> the rows the manager persisted and did not remove */
    private array $rows = [];

    private function createManager(string $schemes = 'papacalin'): AppTokenManager
    {
        $repository = $this->createStub(AppTokenRepository::class);
        $repository->method('findOneByCodeHash')->willReturnCallback(
            fn (string $hash): ?AppToken => array_find($this->rows, static fn (AppToken $row): bool => $hash === $row->getCodeHash())
        );
        $repository->method('findOneByTokenHash')->willReturnCallback(
            fn (string $hash): ?AppToken => array_find($this->rows, static fn (AppToken $row): bool => $hash === $row->getTokenHash())
        );

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $row): void {
            $this->rows[] = $row;
        });
        $entityManager->method('remove')->willReturnCallback(function (object $row): void {
            $this->rows = array_values(array_filter($this->rows, static fn (AppToken $kept): bool => $kept !== $row));
        });

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($schemes);

        return new AppTokenManager($repository, $entityManager, $configService);
    }

    private function code(AppTokenManager $manager): string
    {
        return $manager->createCode($this->createStub(UserInterface::class), AppTokenManager::challengeOf(self::VERIFIER), 'Pixel');
    }

    // The site's own apps and nothing else: a code sent anywhere else is an account given away
    public function testOnlyTheSchemesTheSiteNamesAreSentACode(): void
    {
        $manager = $this->createManager('papacalin, RunAs');

        $this->assertTrue($manager->isAllowedRedirect('papacalin://connect'));
        $this->assertTrue($manager->isAllowedRedirect('runas://connect'));
        $this->assertFalse($manager->isAllowedRedirect('https://evil.example/connect'));
        $this->assertFalse($manager->isAllowedRedirect('other://connect'));
        $this->assertFalse($manager->isAllowedRedirect('not an address'));
    }

    // A site with no app offers no connection at all
    public function testASiteNamingNoSchemeAllowsNothing(): void
    {
        $manager = $this->createManager('');

        $this->assertSame([], $manager->schemes());
        $this->assertFalse($manager->isAllowedRedirect('papacalin://connect'));
    }

    // The whole road: code, token, then the account behind the token
    public function testACodeIsTradedForATokenThatFindsItsAccount(): void
    {
        $manager = $this->createManager();
        $code = $this->code($manager);

        $token = $manager->exchange($code, self::VERIFIER);

        $this->assertNotNull($token);
        $this->assertNotSame($code, $token);
        $this->assertNotNull($manager->find($token));
        $this->assertSame('Pixel', $manager->find($token)->getLabel());
        // Neither the code nor the token is kept as it is
        $this->assertNotSame($token, $this->rows[0]->getTokenHash());
        $this->assertNull($this->rows[0]->getCodeHash());
    }

    // Played once: the second try finds nothing
    public function testACodeWorksOnlyOnce(): void
    {
        $manager = $this->createManager();
        $code = $this->code($manager);

        $this->assertNotNull($manager->exchange($code, self::VERIFIER));
        $this->assertNull($manager->exchange($code, self::VERIFIER));
    }

    // Another app that caught the code on its way cannot trade it, and the code is spent by its try
    public function testACodeIsRefusedToWhoeverDoesNotHoldTheVerifier(): void
    {
        $manager = $this->createManager();
        $code = $this->code($manager);

        $this->assertNull($manager->exchange($code, 'another-verifier'));
        $this->assertNull($manager->exchange($code, self::VERIFIER));
        $this->assertSame([], $this->rows);
    }

    // The time of a redirection, not of a coffee
    public function testACodeLeftTooLongIsRefused(): void
    {
        $manager = $this->createManager();
        $code = $this->code($manager);
        $this->rows[0]->setCodeExpiration(new \DateTimeImmutable('-1 second'));

        $this->assertNull($manager->exchange($code, self::VERIFIER));
    }

    // Signed out, the app's token opens nothing
    public function testARevokedTokenFindsNothing(): void
    {
        $manager = $this->createManager();
        $token = (string) $manager->exchange($this->code($manager), self::VERIFIER);

        $manager->revoke($token);

        $this->assertNull($manager->find($token));
        $this->assertNull($manager->find('a-token-nobody-issued'));
    }

    // The "S256" method of RFC 7636, checked on the example the RFC itself gives
    public function testTheChallengeIsTheOneTheRfcGives(): void
    {
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', AppTokenManager::challengeOf('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));
    }
}
