<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\CaptchaProviderInterface;
use c975L\UiBundle\Service\CaptchaVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

// What the verifier decides whatever the provider: which one is in use, never in "dev", and what it is handed
class CaptchaVerifierTest extends TestCase
{
    private function createVerifier(?string $providerSlug, ?CaptchaProviderInterface $provider = null, string $environment = 'prod'): CaptchaVerifier
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturn(null !== $providerSlug);
        $configService->method('get')->willReturn($providerSlug);

        $requestStack = new RequestStack([new Request(server: ['REMOTE_ADDR' => '203.0.113.7'])]);

        return new CaptchaVerifier($configService, $requestStack, null === $provider ? [] : [$provider], $environment);
    }

    private function createProvider(bool $configured = true, bool $verifies = true): CaptchaProviderInterface
    {
        $provider = $this->createStub(CaptchaProviderInterface::class);
        $provider->method('getSlug')->willReturn('acme');
        $provider->method('isConfigured')->willReturn($configured);
        $provider->method('getSiteKey')->willReturn('site-key');
        $provider->method('verify')->willReturn($verifies);

        return $provider;
    }

    public function testTheProviderNamedAndConfiguredIsInUse(): void
    {
        $provider = $this->createProvider();
        $verifier = $this->createVerifier('acme', $provider);

        $this->assertSame($provider, $verifier->getProvider());
        $this->assertTrue($verifier->isEnabled());
        $this->assertSame('site-key', $verifier->getSiteKey());
    }

    // An empty choice, an unknown slug or a provider missing its keys all leave the forms without a captcha
    public function testNoProviderInUseUnlessNamedRegisteredAndConfigured(): void
    {
        $this->assertNull($this->createVerifier(null, $this->createProvider())->getProvider());
        $this->assertNull($this->createVerifier('', $this->createProvider())->getProvider());
        $this->assertNull($this->createVerifier('other', $this->createProvider())->getProvider());
        $this->assertNull($this->createVerifier('acme', $this->createProvider(configured: false))->getProvider());
        $this->assertFalse($this->createVerifier('acme', $this->createProvider(configured: false))->isConfigured());
        $this->assertNull($this->createVerifier('acme', $this->createProvider(configured: false))->getSiteKey());
    }

    // Off in "dev", whose database copied from production carries keys that refuse localhost, yet configured, what the privacy policy reads
    public function testIsDisabledInDevButStillConfigured(): void
    {
        $verifier = $this->createVerifier('acme', $this->createProvider(), 'dev');

        $this->assertFalse($verifier->isEnabled());
        $this->assertTrue($verifier->isConfigured());
        $this->assertTrue($this->createVerifier('acme', $this->createProvider(), 'test')->isEnabled());
    }

    public function testVerifyHandsTheTokenAndClientIpToTheProvider(): void
    {
        $provider = $this->createMock(CaptchaProviderInterface::class);
        $provider->method('getSlug')->willReturn('acme');
        $provider->method('isConfigured')->willReturn(true);
        $provider->expects($this->once())->method('verify')->with('the-token', '203.0.113.7')->willReturn(true);

        $this->assertTrue($this->createVerifier('acme', $provider)->verify('the-token'));
    }

    // Fails closed: no token, or no provider to ask, rejects the submission
    public function testVerifyRejectsAMissingTokenOrProvider(): void
    {
        $verifier = $this->createVerifier('acme', $this->createProvider());

        $this->assertFalse($verifier->verify(null));
        $this->assertFalse($verifier->verify(''));
        $this->assertFalse($this->createVerifier(null, $this->createProvider())->verify('token'));
        $this->assertFalse($this->createVerifier('acme', $this->createProvider(verifies: false))->verify('token'));
    }
}
