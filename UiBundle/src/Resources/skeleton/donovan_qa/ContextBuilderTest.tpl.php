<?= "<?php\n" ?>
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace <?= $namespace ?>;

use <?= $context_builder_full_name ?>;
use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\UiBundle\Registry\BlockRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class <?= $class_name ?> extends TestCase
{
    private function createBlockRegistry(): BlockRegistry
    {
        $blockRegistry = $this->createStub(BlockRegistry::class);
        $blockRegistry->method('all')->willReturn(['collection' => []]);
        $blockRegistry->method('has')->willReturnCallback(fn (string $kind) => 'collection' === $kind);
        $blockRegistry->method('getLabel')->willReturn('Collection');
        $blockRegistry->method('getDescription')->willReturn('A set of items');

        return $blockRegistry;
    }

    // Translates nothing, so a section shows the key it was given and a drift stays visible
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }

    private function createProvider(): GuidedProjectProviderInterface
    {
        $provider = $this->createStub(GuidedProjectProviderInterface::class);
        $provider->method('getGuidedProjects')->willReturn([
            [
                'slug' => 'ui-media',
                'label' => 'label.project_media',
                'description' => 'description.project_media',
                'translation_domain' => 'ui',
                'order' => 90,
                'steps' => [['label' => 'label.step_open_media']],
            ],
        ]);

        return $provider;
    }

    private function createContextBuilder(): <?= $context_builder_short_name ?>

    {
        return new <?= $context_builder_short_name ?>($this->createBlockRegistry(), [$this->createProvider()], $this->createTranslator());
    }

    public function testContextHoldsOneSectionPerBlockKindAndPerGuidedProject(): void
    {
        $context = $this->createContextBuilder()->context();

        $this->assertStringContainsString('### collection', $context);
        $this->assertStringContainsString('### tour:ui-media', $context);
        $this->assertStringContainsString('label.step_open_media', $context);
    }

    public function testResolveSourcesTellsAGuidedProjectApartFromABlockKind(): void
    {
        $sources = $this->createContextBuilder()->resolveSources(['collection', 'tour:ui-media']);

        $this->assertSame(
            [
                ['label' => 'Collection', 'url' => ''],
                ['label' => 'label.project_media', 'url' => '', 'project' => 'ui-media'],
            ],
            $sources,
        );
    }

    // A model naming something no provider declares any more must cost the citation, not the answer
    public function testResolveSourcesDropsAnUnknownIdentifier(): void
    {
        $this->assertSame([], $this->createContextBuilder()->resolveSources(['ghost', 'tour:ghost']));
    }
}
