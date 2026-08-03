<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\FormBlockDependencyProviderInterface;
use c975L\UiBundle\Registry\FormBlockDependencyRegistry;
use PHPUnit\Framework\TestCase;

class FormBlockDependencyRegistryTest extends TestCase
{
    // An app with no provider installed imports its blocks all the same, seeding nothing
    public function testEnsureDependenciesExistDoesNothingWithoutProviders(): void
    {
        $registry = new FormBlockDependencyRegistry();

        $registry->ensureDependenciesExist(['kind' => 'form', 'data' => ['form' => 'contact']]);

        $this->addToAssertionCount(1);
    }

    public function testEnsureDependenciesExistPassesTheBlockDataToTheProvider(): void
    {
        $blockData = ['kind' => 'form', 'data' => ['form' => 'contact']];

        $provider = $this->createMock(FormBlockDependencyProviderInterface::class);
        $provider->expects($this->once())
            ->method('ensureFormBlockDependenciesExist')
            ->with($blockData);

        $registry = new FormBlockDependencyRegistry();
        $registry->addProvider($provider);

        $registry->ensureDependenciesExist($blockData);
    }

    // Every provider is asked, not just the first: a "register" Form belongs to ConfigBundle and a "contact" one to SiteBundle, and one import can carry both
    public function testEnsureDependenciesExistAsksEveryProvider(): void
    {
        $providerA = $this->createMock(FormBlockDependencyProviderInterface::class);
        $providerA->expects($this->once())->method('ensureFormBlockDependenciesExist');

        $providerB = $this->createMock(FormBlockDependencyProviderInterface::class);
        $providerB->expects($this->once())->method('ensureFormBlockDependenciesExist');

        $registry = new FormBlockDependencyRegistry();
        $registry->addProvider($providerA);
        $registry->addProvider($providerB);

        $registry->ensureDependenciesExist(['kind' => 'form', 'data' => ['form' => 'register']]);
    }
}
