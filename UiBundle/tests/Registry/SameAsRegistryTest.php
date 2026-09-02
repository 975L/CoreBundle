<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\SameAsProviderInterface;
use c975L\UiBundle\Registry\SameAsRegistry;
use PHPUnit\Framework\TestCase;

class SameAsRegistryTest extends TestCase
{
    private function createProvider(array $urls): SameAsProviderInterface
    {
        $provider = $this->createStub(SameAsProviderInterface::class);
        $provider->method('getSameAs')->willReturn($urls);

        return $provider;
    }

    public function testAllReturnsEmptyArrayWhenNoProviders(): void
    {
        $registry = new SameAsRegistry();

        $this->assertSame([], $registry->all());
    }

    public function testAllMergesUrlsFromEveryProviderInDeclarationOrder(): void
    {
        $registry = new SameAsRegistry();
        $registry->addProvider($this->createProvider(['https://www.google.com/maps?cid=1']));
        $registry->addProvider($this->createProvider(['https://facebook.com/example']));

        $this->assertSame(['https://www.google.com/maps?cid=1', 'https://facebook.com/example'], $registry->all());
    }

    // The same profile named by two bundles would otherwise be published twice, which reads as two entities
    public function testAllDeduplicatesUrlsAcrossProviders(): void
    {
        $registry = new SameAsRegistry();
        $registry->addProvider($this->createProvider(['https://www.google.com/maps?cid=1', 'https://facebook.com/example']));
        $registry->addProvider($this->createProvider(['https://facebook.com/example', 'https://fr.linkedin.com/company/example']));

        $this->assertSame(['https://www.google.com/maps?cid=1', 'https://facebook.com/example', 'https://fr.linkedin.com/company/example'], $registry->all());
    }

    // A url left empty in a provider's own config reaches the registry as a blank string, and would publish an empty node
    public function testAllDropsEmptyUrlsAndTrimsTheOthers(): void
    {
        $registry = new SameAsRegistry();
        $registry->addProvider($this->createProvider(['', '   ', '  https://facebook.com/example  ']));

        $this->assertSame(['https://facebook.com/example'], $registry->all());
    }

    // Trimming is what makes the de-duplication hold: the same url padded differently by two providers is one profile
    public function testAllDeduplicatesUrlsDifferingOnlyByTheirPadding(): void
    {
        $registry = new SameAsRegistry();
        $registry->addProvider($this->createProvider(['https://facebook.com/example']));
        $registry->addProvider($this->createProvider([' https://facebook.com/example']));

        $this->assertSame(['https://facebook.com/example'], $registry->all());
    }
}
