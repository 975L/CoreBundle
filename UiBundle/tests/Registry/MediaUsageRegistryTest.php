<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\MediaUsageProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Registry\MediaUsageRegistry;
use c975L\UiBundle\Service\BlockMediaUsageProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class MediaUsageRegistryTest extends TestCase
{
    public function testGetUsagesReturnsEmptyArrayWhenNoProviders(): void
    {
        $registry = new MediaUsageRegistry();

        $this->assertSame([], $registry->getUsages([]));
    }

    public function testGetUsagesReturnsSingleProviderResult(): void
    {
        $provider = $this->createStub(MediaUsageProviderInterface::class);
        $provider->method('getUsages')->willReturn([1 => [['label' => 'a', 'url' => null]]]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($provider);

        $this->assertSame([1 => [['label' => 'a', 'url' => null]]], $registry->getUsages([]));
    }

    // Usages contributed by different providers for the same media id are merged, not overwritten
    public function testGetUsagesMergesEntriesFromMultipleProvidersForSameMediaId(): void
    {
        $providerA = $this->createStub(MediaUsageProviderInterface::class);
        $providerA->method('getUsages')->willReturn([1 => [['label' => 'from-a', 'url' => null]]]);

        $providerB = $this->createStub(MediaUsageProviderInterface::class);
        $providerB->method('getUsages')->willReturn([1 => [['label' => 'from-b', 'url' => null]]]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($providerA);
        $registry->addProvider($providerB);

        $this->assertSame(
            [1 => [['label' => 'from-a', 'url' => null], ['label' => 'from-b', 'url' => null]]],
            $registry->getUsages([])
        );
    }

    public function testGetUsagesKeepsEntriesForDifferentMediaIdsSeparate(): void
    {
        $provider = $this->createStub(MediaUsageProviderInterface::class);
        $provider->method('getUsages')->willReturn([
            1 => [['label' => 'a', 'url' => null]],
            2 => [['label' => 'b', 'url' => null]],
        ]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($provider);

        $usages = $registry->getUsages([]);
        $this->assertArrayHasKey(1, $usages);
        $this->assertArrayHasKey(2, $usages);
    }

    // What the media library leaves out of its gallery, and svg-fonts out of its run: drawn by nothing the site serves, yet used, so not deletable either
    public function testGetBinnedOnlyMediaIdsKeepsAMediaWhoseEveryUsageIsBinned(): void
    {
        $provider = $this->createStub(MediaUsageProviderInterface::class);
        $provider->method('getUsages')->willReturn([
            1 => [['label' => 'binned', 'url' => null, 'binned' => true]],
        ]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($provider);

        $this->assertSame([1], $registry->getBinnedOnlyMediaIds([]));
    }

    // One live usage is enough: the media is drawn somewhere, whatever else holds it
    public function testGetBinnedOnlyMediaIdsLeavesOutAMediaWithOneLiveUsage(): void
    {
        $provider = $this->createStub(MediaUsageProviderInterface::class);
        $provider->method('getUsages')->willReturn([
            1 => [
                ['label' => 'binned', 'url' => null, 'binned' => true],
                ['label' => 'live', 'url' => null, 'binned' => false],
            ],
        ]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($provider);

        $this->assertSame([], $registry->getBinnedOnlyMediaIds([]));
    }

    // A media nobody uses has no owner to be in the bin, and hiding it would hide the very rows the library exists to find again
    public function testGetBinnedOnlyMediaIdsLeavesOutAMediaWithNoUsageAtAll(): void
    {
        $provider = $this->createStub(MediaUsageProviderInterface::class);
        $provider->method('getUsages')->willReturn([1 => []]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($provider);

        $this->assertSame([], $registry->getBinnedOnlyMediaIds([]));
    }

    // The verdict is read across providers, not per provider: one bundle knowing a live usage saves the media another only knows binned
    public function testGetBinnedOnlyMediaIdsReadsEveryProviderTogether(): void
    {
        $providerA = $this->createStub(MediaUsageProviderInterface::class);
        $providerA->method('getUsages')->willReturn([1 => [['label' => 'binned', 'url' => null, 'binned' => true]]]);

        $providerB = $this->createStub(MediaUsageProviderInterface::class);
        $providerB->method('getUsages')->willReturn([1 => [['label' => 'live', 'url' => null, 'binned' => false]]]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($providerA);
        $registry->addProvider($providerB);

        $this->assertSame([], $registry->getBinnedOnlyMediaIds([]));
    }

    // BlockMediaUsageProvider reports every media hanging off a block without ever knowing what owns that block, so its usage carries no key at all - counting it as live would bury the answer of the provider that does know
    public function testGetBinnedOnlyMediaIdsIgnoresAUsageWithNoVerdict(): void
    {
        $knowing = $this->createStub(MediaUsageProviderInterface::class);
        $knowing->method('getUsages')->willReturn([1 => [['label' => 'binned page', 'url' => null, 'binned' => true]]]);

        $baseline = $this->createStub(MediaUsageProviderInterface::class);
        $baseline->method('getUsages')->willReturn([1 => [['label' => 'attached to block', 'url' => null]]]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($knowing);
        $registry->addProvider($baseline);

        $this->assertSame([1], $registry->getBinnedOnlyMediaIds([]));
    }

    // Nobody having a verdict is not a verdict: a media only the baseline provider reports stays visible rather than hidden on a guess
    public function testGetBinnedOnlyMediaIdsLeavesOutAMediaNoProviderHasAVerdictOn(): void
    {
        $baseline = $this->createStub(MediaUsageProviderInterface::class);
        $baseline->method('getUsages')->willReturn([1 => [['label' => 'attached to block', 'url' => null]]]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($baseline);

        $this->assertSame([], $registry->getBinnedOnlyMediaIds([]));
    }

    // The real pair, as a site runs them: UiBundle reports every media hanging off a block, and would sink the verdict of the bundle that owns the page if its silence counted as "live" - which is exactly what a media of a binned page looks like
    public function testGetBinnedOnlyMediaIdsWithTheRealBlockProviderBeside(): void
    {
        $block = new Block();
        new \ReflectionProperty(Block::class, 'id')->setValue($block, 355);

        $media = new Media();
        new \ReflectionProperty(Media::class, 'id')->setValue($media, 1);
        $media->setBlock($block);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $owningBundle = $this->createStub(MediaUsageProviderInterface::class);
        $owningBundle->method('getUsages')->willReturn([1 => [['label' => 'binned page', 'url' => null, 'binned' => true]]]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider(new BlockMediaUsageProvider($translator));
        $registry->addProvider($owningBundle);

        $this->assertSame([1], $registry->getBinnedOnlyMediaIds([$media]));
    }
}
