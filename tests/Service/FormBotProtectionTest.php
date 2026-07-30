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
use c975L\UiBundle\Service\FormBotProtection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

// Merges what used to be c975L\SiteBundle\Service\FormBotProtection's tests (timing) and c975L\ContactFormBundle\Service\ContactFormService's own rotating-honeypot behavior (not previously unit-tested in isolation)
class FormBotProtectionTest extends TestCase
{
    private function requestWithSession(): Request
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    // Simulates the raw submitted honeypot value, isSuspicious() reading it off the request directly
    private function setHoneypotValue(Request $request, string $formName, string $fieldName, string $value): void
    {
        $request->request->set($formName, [$fieldName => $value]);
    }

    public function testHoneypotFieldNameIsStableAcrossCalls(): void
    {
        $botProtection = new FormBotProtection($this->createStub(ConfigServiceInterface::class));
        $request = $this->requestWithSession();

        $first = $botProtection->honeypotFieldName($request);

        $this->assertSame($first, $botProtection->honeypotFieldName($request));
    }

    public function testHoneypotFieldNameIsAPrefixSuffixCombination(): void
    {
        $botProtection = new FormBotProtection($this->createStub(ConfigServiceInterface::class));

        $name = $botProtection->honeypotFieldName($this->requestWithSession());

        $this->assertMatchesRegularExpression(
            '/^(user|account|client|contact|person|profile)_(name|info|data|field|input|details)$/',
            $name
        );
    }

    public function testHoneypotLabelIsStableAcrossCalls(): void
    {
        $botProtection = new FormBotProtection($this->createStub(ConfigServiceInterface::class));
        $request = $this->requestWithSession();

        $first = $botProtection->honeypotLabel($request);

        $this->assertSame($first, $botProtection->honeypotLabel($request));
    }

    public function testAddHoneypotFieldAddsUnmappedOffscreenField(): void
    {
        $botProtection = new FormBotProtection($this->createStub(ConfigServiceInterface::class));
        $request = $this->requestWithSession();
        $expectedName = $botProtection->honeypotFieldName($request);
        $expectedLabel = $botProtection->honeypotLabel($request);

        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects($this->once())
            ->method('add')
            ->with(
                $expectedName,
                null,
                $this->callback(static function (array $options) use ($expectedLabel): bool {
                    return $expectedLabel === $options['label']
                        // The decoy label must never reach the translator: it's picked at random and has no translation, which would be reported as a missing key on every form carrying the honeypot
                        && false === $options['translation_domain']
                        && false === $options['required']
                        && false === $options['mapped']
                        && '' === $options['data'];
                })
            )
            ->willReturn($builder);

        $botProtection->addHoneypotField($builder, $request);
    }

    // Regression guard: outside a live HTTP request the honeypot is skipped, not a TypeError
    public function testAddHoneypotFieldDoesNothingWhenRequestIsNull(): void
    {
        $botProtection = new FormBotProtection($this->createStub(ConfigServiceInterface::class));

        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects($this->never())->method('add');

        $botProtection->addHoneypotField($builder, null);
    }

    public function testStartTimerOnlySetsTimestampOnce(): void
    {
        $botProtection = new FormBotProtection($this->createStub(ConfigServiceInterface::class));
        $request = $this->requestWithSession();

        $botProtection->startTimer($request, 'test_started_at');
        $firstTimestamp = $request->getSession()->get('test_started_at');

        $botProtection->startTimer($request, 'test_started_at');

        $this->assertSame($firstTimestamp, $request->getSession()->get('test_started_at'));
    }

    public function testIsSuspiciousWhenHoneypotFilled(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-form-delay', 3]]);

        $request = $this->requestWithSession();
        $request->getSession()->set('test_started_at', time() - 60);
        $request->getSession()->set('ui_honeypot_field', 'website');
        $this->setHoneypotValue($request, 'test_form', 'website', 'https://spam.example');

        $botProtection = new FormBotProtection($configService);

        $this->assertTrue($botProtection->isSuspicious($request, 'test_form', 'test_started_at'));
    }

    public function testIsSuspiciousWhenSubmittedFasterThanDelay(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-form-delay', 60]]);

        $request = $this->requestWithSession();
        $request->getSession()->set('test_started_at', time());
        $request->getSession()->set('ui_honeypot_field', 'website');
        $this->setHoneypotValue($request, 'test_form', 'website', '');

        $botProtection = new FormBotProtection($configService);

        $this->assertTrue($botProtection->isSuspicious($request, 'test_form', 'test_started_at'));
    }

    public function testIsSuspiciousFalseForLegitimateSubmission(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-form-delay', 3]]);

        $request = $this->requestWithSession();
        $request->getSession()->set('test_started_at', time() - 60);
        $request->getSession()->set('ui_honeypot_field', 'website');
        $this->setHoneypotValue($request, 'test_form', 'website', '');

        $botProtection = new FormBotProtection($configService);

        $this->assertFalse($botProtection->isSuspicious($request, 'test_form', 'test_started_at'));
    }

    // "site-form-delay" isn't seeded when c975l/config-bundle hasn't loaded it yet - falls back to 7s
    public function testIsSuspiciousFallsBackTo7SecondsWhenDelayNotSeeded(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-form-delay', null]]);

        $request = $this->requestWithSession();
        $request->getSession()->set('test_started_at', time());
        $request->getSession()->set('ui_honeypot_field', 'website');
        $this->setHoneypotValue($request, 'test_form', 'website', '');

        $botProtection = new FormBotProtection($configService);

        $this->assertTrue($botProtection->isSuspicious($request, 'test_form', 'test_started_at'));
    }

    public function testIsSuspiciousRemovesTimestampAndHoneypotFromSession(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-form-delay', 3]]);

        $request = $this->requestWithSession();
        $request->getSession()->set('test_started_at', time() - 60);
        $request->getSession()->set('ui_honeypot_field', 'website');
        $request->getSession()->set('ui_honeypot_label', 'Website');
        $this->setHoneypotValue($request, 'test_form', 'website', '');

        $botProtection = new FormBotProtection($configService);
        $botProtection->isSuspicious($request, 'test_form', 'test_started_at');

        $this->assertFalse($request->getSession()->has('test_started_at'));
        $this->assertFalse($request->getSession()->has('ui_honeypot_field'));
        $this->assertFalse($request->getSession()->has('ui_honeypot_label'));
    }
}
