<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\EmailTemplateProviderInterface;
use c975L\UiBundle\Registry\EmailTemplateProviderRegistry;
use PHPUnit\Framework\TestCase;

// Every transactional e-mail the installed bundles say a site should be able to send - the one list the seeder, the ensure command and the health check all read
class EmailTemplateProviderRegistryTest extends TestCase
{
    public function testASiteBringingNoProviderDeclaresNothing(): void
    {
        $this->assertSame([], new EmailTemplateProviderRegistry()->getDeclaredTemplates());
    }

    public function testEveryProviderDeclarationIsGathered(): void
    {
        $registry = new EmailTemplateProviderRegistry();
        $registry->addProvider($this->provider(['shop_order' => ['fr' => []]]));
        $registry->addProvider($this->provider(['account_validation' => ['en' => []]]));

        $this->assertSame(['account_validation', 'shop_order'], array_keys($registry->getDeclaredTemplates()));
    }

    // Sorted by name, so the command and the health check list the e-mails the same way whatever order the bundles were registered in
    public function testTheDeclarationsAreSortedByName(): void
    {
        $registry = new EmailTemplateProviderRegistry();
        $registry->addProvider($this->provider(['zeta' => ['fr' => []], 'alpha' => ['fr' => []]]));

        $this->assertSame(['alpha', 'zeta'], array_keys($registry->getDeclaredTemplates()));
    }

    // How an app overrides what a bundle ships without the bundle knowing: it declares the same name later
    public function testALaterProviderWinsOnANameAnEarlierOneDeclared(): void
    {
        $registry = new EmailTemplateProviderRegistry();
        $registry->addProvider($this->provider(['password_reset' => ['fr' => [['text', null, null, 'bundle', null, null]]]]));
        $registry->addProvider($this->provider(['password_reset' => ['fr' => [['text', null, null, 'app', null, null]]]]));

        $declared = $registry->getDeclaredTemplates();

        $this->assertSame('app', $declared['password_reset']['fr'][0][3]);
    }

    // A name only one of them declares is kept whole rather than dropped by the override
    public function testANameOnlyOneProviderDeclaresSurvives(): void
    {
        $registry = new EmailTemplateProviderRegistry();
        $registry->addProvider($this->provider(['password_reset' => ['fr' => []], 'shop_order' => ['fr' => []]]));
        $registry->addProvider($this->provider(['password_reset' => ['en' => []]]));

        $declared = $registry->getDeclaredTemplates();

        $this->assertSame(['password_reset', 'shop_order'], array_keys($declared));
        $this->assertSame(['en'], array_keys($declared['password_reset']));
    }

    /**
     * @param array<string, array<string, list<array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}>>> $templates
     */
    private function provider(array $templates): EmailTemplateProviderInterface
    {
        $provider = $this->createStub(EmailTemplateProviderInterface::class);
        $provider->method('getEmailTemplates')->willReturn($templates);

        return $provider;
    }
}
