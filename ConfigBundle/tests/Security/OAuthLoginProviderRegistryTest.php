<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Security;

use c975L\ConfigBundle\Security\GoogleOAuthLoginProvider;
use c975L\ConfigBundle\Security\OAuthLoginProviderRegistry;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\OAuthLoginClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

class OAuthLoginProviderRegistryTest extends TestCase
{
    /**
     * @param array<string, mixed> $configs
     */
    private function createRegistry(array $configs): OAuthLoginProviderRegistry
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug): mixed => $configs[$slug] ?? null);

        return new OAuthLoginProviderRegistry(
            [new GoogleOAuthLoginProvider(new MockHttpClient())],
            new OAuthLoginClient(new MockHttpClient(), $configService),
        );
    }

    private function credentials(): array
    {
        return [
            'login-google-oauth-client-id' => 'an-id.apps.googleusercontent.com',
            'login-google-oauth-client-secret' => 'a-secret',
        ];
    }

    // The state every site starts in: the login page then looks exactly as it did before any of this existed
    public function testASiteThatConfiguredNothingEnablesNothing(): void
    {
        $registry = $this->createRegistry([]);

        $this->assertSame([], $registry->enabled());
        $this->assertNull($registry->get('google'));
    }

    public function testAConfiguredProviderIsEnabledAndResolvedByItsKey(): void
    {
        $registry = $this->createRegistry($this->credentials());

        $this->assertCount(1, $registry->enabled());
        $this->assertInstanceOf(GoogleOAuthLoginProvider::class, $registry->get('google'));
    }

    // An url guessed for a provider this site never enabled says no more than a typo does - both end as the same 404
    public function testAnUnknownKeyResolvesToNothing(): void
    {
        $this->assertNull($this->createRegistry($this->credentials())->get('facebook'));
    }
}
