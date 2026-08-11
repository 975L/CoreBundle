<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\UiBundle\Contract\BlockLocationProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Management\LegalModelDriftHealthCheckProvider;
use c975L\UiBundle\Registry\BlockLocationRegistry;
use c975L\UiBundle\Repository\BlockRepository;
use c975L\UiBundle\Service\LegalModelCatalog;
use c975L\UiBundle\Service\LegalModelCustomizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LegalModelDriftHealthCheckProviderTest extends TestCase
{
    // The id matters here: both the provider and its location registry key everything on it
    private function createBlock(array $data, int $id = 1): Block
    {
        $block = new Block();
        $block->setKind('legal_model');
        $block->setData($data);
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, $id);

        return $block;
    }

    private function createBlockRepository(array $blocks): BlockRepository
    {
        $repository = $this->createStub(BlockRepository::class);
        $repository->method('findByKind')->willReturn($blocks);

        return $repository;
    }

    // Stands in for whichever bundle owns the block (SiteBundle's Page in practice) - keyed by block id, and a null id (an unpersisted block, as built here) is exactly the "nobody claims it" case
    private function createLocationRegistry(?string $url, string $label = 'Legal notice'): BlockLocationRegistry
    {
        $registry = new BlockLocationRegistry();

        $provider = $this->createStub(BlockLocationProviderInterface::class);
        $provider->method('getLocations')->willReturnCallback(
            static fn (array $blocks): array => array_reduce(
                $blocks,
                static function (array $carry, Block $block) use ($url, $label): array {
                    $carry[$block->getId()] = ['label' => $label, 'url' => $url, 'published' => true];

                    return $carry;
                },
                [],
            )
        );
        $registry->addProvider($provider);

        return $registry;
    }

    private function createCustomizer(array $drifted): LegalModelCustomizer
    {
        $customizer = $this->createStub(LegalModelCustomizer::class);
        $customizer->method('drifted')->willReturn($drifted);

        return $customizer;
    }

    // Appends the parameters rather than substituting them like the other provider tests do: the summary is built from a key whose own text lives in the XLF files, so a strtr() stub would swallow what it carries
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = []) => trim($id . ' ' . implode(' ', $parameters))
        );

        return $translator;
    }

    private function createProvider(array $blocks, array $drifted, ?string $url = 'https://example.com/pages/legal-notice'): LegalModelDriftHealthCheckProvider
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/management/legal-model/1/customize');

        return new LegalModelDriftHealthCheckProvider(
            $this->createBlockRepository($blocks),
            $this->createLocationRegistry($url),
            $this->createCustomizer($drifted),
            new LegalModelCatalog(),
            $urlGenerator,
            $this->createTranslator(),
        );
    }

    public function testGetKindReturnsLegalModel(): void
    {
        $this->assertSame('legal_model', $this->createProvider([], [])->getKind());
    }

    public function testRunChecksReturnsNothingWithoutAnyLegalModelBlock(): void
    {
        $this->assertSame([], $this->createProvider([], [])->runChecks());
    }

    // The common case: a site that customized nothing must not clutter the health check table
    public function testRunChecksReturnsNothingWhenNoSectionDrifted(): void
    {
        $blocks = [$this->createBlock(['model' => 'france/legal-notice'])];

        $this->assertSame([], $this->createProvider($blocks, [])->runChecks());
    }

    // A block whose model is not one the bundle ships is skipped rather than reported against a template path
    public function testRunChecksSkipsAnUnknownModel(): void
    {
        $blocks = [$this->createBlock(['model' => 'elsewhere/invented'])];
        $drifted = ['one' => ['id' => 'one', 'title' => 'One', 'html' => '', 'hash' => 'abc', 'level' => 2]];

        $this->assertSame([], $this->createProvider($blocks, $drifted)->runChecks());
    }

    // No public url (unpublished owner, "site-url" not set yet, or no bundle owning blocks at all): a health check row is keyed on the address it tested, so there is nothing to report
    public function testRunChecksSkipsABlockWithNoPublicUrl(): void
    {
        $blocks = [$this->createBlock(['model' => 'france/legal-notice'])];
        $drifted = ['one' => ['id' => 'one', 'title' => 'One', 'html' => '', 'hash' => 'abc', 'level' => 2]];

        $this->assertSame([], $this->createProvider($blocks, $drifted, null)->runChecks());
    }

    // Reported as ok on purpose: it feeds neither the dashboard alerts nor the digest email
    public function testRunChecksReportsDriftAsOkAndNamesTheSections(): void
    {
        $blocks = [$this->createBlock(['model' => 'france/legal-notice'])];
        $drifted = [
            'publisher' => ['id' => 'publisher', 'title' => 'Publisher', 'html' => '', 'hash' => 'abc', 'level' => 2],
            'host' => ['id' => 'host', 'title' => 'Host', 'html' => '', 'hash' => 'def', 'level' => 2],
        ];

        $result = $this->createProvider($blocks, $drifted)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
        $this->assertSame('Legal notice', $result['label']);
        $this->assertSame('https://example.com/pages/legal-notice', $result['url']);
        $this->assertStringContainsString('Publisher · Host', $result['summary']);
        $this->assertStringContainsString('label.legal_notice', $result['summary']);
        $this->assertSame('/management/legal-model/1/customize', $result['editUrl']);
    }

    // A drifted unit with no heading of its own is named by its identifier rather than by an empty string
    public function testRunChecksNamesAHeadinglessSectionByItsIdentifier(): void
    {
        $blocks = [$this->createBlock(['model' => 'france/legal-notice'])];
        $drifted = ['intro' => ['id' => 'intro', 'title' => '', 'html' => '', 'hash' => 'abc', 'level' => 1]];

        $result = $this->createProvider($blocks, $drifted)->runChecks()[0];

        $this->assertStringContainsString('intro', $result['summary']);
    }
}
