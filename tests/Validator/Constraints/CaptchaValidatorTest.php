<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Validator\Constraints;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\CaptchaVerifier;
use c975L\UiBundle\Validator\Constraints\Captcha;
use c975L\UiBundle\Validator\Constraints\CaptchaValidator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class CaptchaValidatorTest extends ConstraintValidatorTestCase
{
    private const KEYS = [
        'recaptcha3-site-key' => 'site-key',
        'recaptcha3-secret-key' => 'secret-key',
    ];

    /** @var array<string, mixed> */
    private array $configValues = self::KEYS;

    private bool $tokenAccepted = true;

    protected function createValidator(): CaptchaValidator
    {
        $configValues = $this->configValues;

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnCallback(static fn (string $key) => array_key_exists($key, $configValues));
        $configService->method('get')->willReturnCallback(static fn (string $key) => $configValues[$key] ?? null);

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $httpClient = new MockHttpClient(new MockResponse(json_encode(['success' => $this->tokenAccepted, 'score' => 0.9], \JSON_THROW_ON_ERROR)));

        return new CaptchaValidator(new CaptchaVerifier($httpClient, $configService, $requestStack));
    }

    // The verifier is built in createValidator(), which setUp() already ran - a test needing other config rebuilds it before validating
    private function reconfigure(array $configValues, bool $tokenAccepted = true): void
    {
        $this->configValues = $configValues;
        $this->tokenAccepted = $tokenAccepted;
        $this->validator = $this->createValidator();
    }

    public function testVerifiedTokenIsValid(): void
    {
        $this->validate('token', new Captcha());

        $this->assertNoViolation();
    }

    public function testTokenGoogleTurnsDownRaisesViolation(): void
    {
        $this->reconfigure(self::KEYS, tokenAccepted: false);
        $constraint = new Captcha();

        $this->validate('token', $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testMissingTokenRaisesViolation(): void
    {
        $constraint = new Captcha();

        $this->validate(null, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    // Nothing was rendered to solve, so there is nothing to hold against the visitor
    public function testNoViolationWhenNoKeysAreConfigured(): void
    {
        $this->reconfigure([]);

        $this->validate(null, new Captcha());

        $this->assertNoViolation();
    }

    public function testAConstraintItDoesNotHandleIsRejected(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate('token', new NotBlank());
    }
}
