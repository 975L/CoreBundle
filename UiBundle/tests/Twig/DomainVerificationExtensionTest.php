<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Twig\DomainVerificationExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Attribute\AsTwigFunction;

// Every page's head prints what this returns, so a pair read wrong either loses a service's proof of ownership or prints a tag with nothing in it
class DomainVerificationExtensionTest extends TestCase
{
    // The name is written in an attribute and nowhere else, so it is what the layout calling it relies on
    public function testTheFunctionIsRegisteredUnderTheNameTheLayoutCalls(): void
    {
        $attributes = new \ReflectionMethod(DomainVerificationExtension::class, 'getVerifications')->getAttributes(AsTwigFunction::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('domain_verifications', $attributes[0]->getArguments()[0]);
    }

    // Pairs are trimmed, and an entry missing its name or its value is dropped
    #[DataProvider('provideValues')]
    public function testItReadsTheNameValuePairs(?string $value, array $expected): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($value);

        $this->assertSame($expected, new DomainVerificationExtension($configService)->getVerifications());
    }

    public static function provideValues(): iterable
    {
        yield 'not set' => [null, []];
        yield 'blank' => ['  ', []];
        yield 'one pair' => ['p:domain_verify=abc123', ['p:domain_verify' => 'abc123']];
        yield 'several, spaced' => [
            ' p:domain_verify = abc123 , facebook-domain-verification=xyz ,msvalidate.01=0F1E',
            ['p:domain_verify' => 'abc123', 'facebook-domain-verification' => 'xyz', 'msvalidate.01' => '0F1E'],
        ];
        yield 'an equal sign inside the value' => ['google-site-verification=a=b', ['google-site-verification' => 'a=b']];
        yield 'no value, no name, no equal sign' => ['p:domain_verify=, =xyz, alone, , msvalidate.01=0F1E', ['msvalidate.01' => '0F1E']];
    }
}
