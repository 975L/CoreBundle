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
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GoogleOAuthLoginProviderTest extends TestCase
{
    private function createProvider(MockHttpClient $httpClient): GoogleOAuthLoginProvider
    {
        return new GoogleOAuthLoginProvider($httpClient);
    }

    private function userinfo(array $payload, int $status = 200): MockHttpClient
    {
        return new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode($payload), ['http_code' => $status]));
    }

    // Asked for nothing beyond a login: both scopes are non-sensitive, which is what lets a site publish its Google application without any review
    public function testItAsksForTheTwoScopesGooglePublishesWithoutReview(): void
    {
        $this->assertSame('openid email', $this->createProvider(new MockHttpClient())->getScope());
        $this->assertSame('google', $this->createProvider(new MockHttpClient())->getKey());
        $this->assertSame('login-google-oauth-client-id', $this->createProvider(new MockHttpClient())->getClientIdSlug());
        $this->assertSame('login-google-oauth-client-secret', $this->createProvider(new MockHttpClient())->getClientSecretSlug());
    }

    public function testTheAccessTokenReadsBackAVerifiedAddress(): void
    {
        $sent = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = ['method' => $method, 'url' => $url, 'headers' => $options['headers'] ?? []];

            return new MockResponse(json_encode(['email' => 'visitor@example.test', 'email_verified' => true]), ['http_code' => 200]);
        });

        $identity = $this->createProvider($httpClient)->fetchIdentity('an-access-token');

        $this->assertSame('visitor@example.test', $identity?->email);
        $this->assertTrue($identity->emailVerified);
        $this->assertSame('GET', $sent['method']);
        $this->assertSame(GoogleOAuthLoginProvider::USERINFO_ENDPOINT, $sent['url']);
        $this->assertContains('Authorization: Bearer an-access-token', $sent['headers']);
    }

    // Google answers a real boolean, other OpenID providers a "true" string - and anything else than a clear yes counts as no, OAuthUserResolver refusing what isn't vouched for
    public function testAVerifiedFlagIsReadWhicheverWayItIsWritten(): void
    {
        $this->assertTrue($this->createProvider($this->userinfo(['email' => 'a@example.test', 'email_verified' => 'true']))->fetchIdentity('t')?->emailVerified);
        $this->assertFalse($this->createProvider($this->userinfo(['email' => 'a@example.test', 'email_verified' => false]))->fetchIdentity('t')?->emailVerified);
        $this->assertFalse($this->createProvider($this->userinfo(['email' => 'a@example.test']))->fetchIdentity('t')?->emailVerified);
    }

    // Nothing to log in with: refused rather than guessed
    public function testAnAnswerWithoutAnEmailIsNoIdentityAtAll(): void
    {
        $this->assertNull($this->createProvider($this->userinfo(['sub' => '12345']))->fetchIdentity('an-access-token'));
        $this->assertNull($this->createProvider($this->userinfo(['error' => 'invalid_token'], 401))->fetchIdentity('an-access-token'));
    }

    // A provider that is down or unreachable ends the login, it doesn't end the request with a 500
    public function testATransportFailureIsReportedAsNoIdentity(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            throw new \RuntimeException('connection reset');
        });

        $this->assertNull($this->createProvider($httpClient)->fetchIdentity('an-access-token'));
    }
}
