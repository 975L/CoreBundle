<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\FormPageUrlProviderInterface;
use c975L\UiBundle\Registry\FormPageUrlRegistry;
use PHPUnit\Framework\TestCase;

class FormPageUrlRegistryTest extends TestCase
{
    // Null is what tells the caller to fall back on the bare "ui_form_submit" route
    public function testGetReturnsNullWhenNoProviders(): void
    {
        $registry = new FormPageUrlRegistry();

        $this->assertNull($registry->get('contact'));
    }

    public function testGetDelegatesToRegisteredProvider(): void
    {
        $registry = new FormPageUrlRegistry();
        $registry->addProvider($this->provider(['contact' => '/en/contact-us']));

        $this->assertSame('/en/contact-us', $registry->get('contact'));
    }

    // A provider owning none of the requested names answers null, so the next one gets asked
    public function testGetAsksTheNextProviderWhenTheFirstHasNoPage(): void
    {
        $registry = new FormPageUrlRegistry();
        $registry->addProvider($this->provider(['contact' => '/en/contact-us']));
        $registry->addProvider($this->provider(['register' => '/en/sign-up']));

        $this->assertSame('/en/sign-up', $registry->get('register'));
    }

    // The first provider with an answer wins - two bundles displaying the same Form is not a configuration to arbitrate here
    public function testGetKeepsTheFirstAnswerWhenSeveralProvidersHaveOne(): void
    {
        $registry = new FormPageUrlRegistry();
        $registry->addProvider($this->provider(['contact' => '/from-a']));
        $registry->addProvider($this->provider(['contact' => '/from-b']));

        $this->assertSame('/from-a', $registry->get('contact'));
    }

    public function testGetReturnsNullWhenNoProviderKnowsTheName(): void
    {
        $registry = new FormPageUrlRegistry();
        $registry->addProvider($this->provider(['contact' => '/en/contact-us']));

        $this->assertNull($registry->get('newsletter'));
    }

    // A provider answering from a fixed name => url map
    private function provider(array $urls): FormPageUrlProviderInterface
    {
        $provider = $this->createStub(FormPageUrlProviderInterface::class);
        $provider->method('getFormPageUrl')->willReturnCallback(
            static fn (string $formName): ?string => $urls[$formName] ?? null
        );

        return $provider;
    }
}
