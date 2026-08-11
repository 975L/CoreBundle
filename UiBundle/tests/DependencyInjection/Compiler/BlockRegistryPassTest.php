<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\BlockRegistryPass;
use c975L\UiBundle\Registry\BlockRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BlockRegistryPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new BlockRegistryPass()->process($container);

        $this->addToAssertionCount(1);
    }

    public function testProcessRegistersBlockWithDeclaredAttributesAndDefaults(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('block.article')->addTag('ui.block', [
            'kind' => 'article',
            'label' => 'label.article',
            'form' => 'App\\Form\\ArticleType',
            'template' => 'article.html.twig',
        ]);

        new BlockRegistryPass()->process($container);

        $calls = $container->getDefinition(BlockRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('register', $calls[0][0]);
        $this->assertSame(
            [
                'article',
                'label.article',
                'App\\Form\\ArticleType',
                'article.html.twig',
                'label.category_general',
                [],
                'ui',
                '',
                true,
                0,
                true,
                [],
                false,
                false,
                '',
                false,
                BlockRegistry::SLOT_CONTEXT,
                '',
            ],
            $calls[0][1]
        );
    }

    // The originating bundle is derived from the template's "@c975LXxx/..." Twig namespace, not a dedicated tag attribute - every bundle already declares this namespace for its own templates
    public function testProcessDerivesBundleFromTemplateNamespace(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('site.block.legal_model')->addTag('ui.block', [
            'kind' => 'legal_model',
            'label' => 'label.legal_model',
            'form' => 'c975L\\SiteBundle\\Form\\Block\\LegalModelType',
            'template' => '@c975LSite/blocks/LegalModel.html.twig',
        ]);

        new BlockRegistryPass()->process($container);

        $calls = $container->getDefinition(BlockRegistry::class)->getMethodCalls();
        $this->assertSame('Site', $calls[0][1][14]);
    }

    // A template outside the "@c975LXxx/..." convention (or a plain path) yields an empty bundle key instead of throwing - keeps register() usable from app-level/test code that doesn't follow it
    public function testProcessBundleIsEmptyWhenTemplateHasNoC975LNamespace(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('block.custom')->addTag('ui.block', [
            'kind' => 'custom',
            'label' => 'label.custom',
            'form' => 'App\\Form\\CustomType',
            'template' => '@App/blocks/custom.html.twig',
        ]);

        new BlockRegistryPass()->process($container);

        $calls = $container->getDefinition(BlockRegistry::class)->getMethodCalls();
        $this->assertSame('', $calls[0][1][14]);
    }

    // media_required defaults to false and can be explicitly enabled, same boolean-flag parsing as pickable/cacheable
    public function testProcessParsesMediaRequiredFlag(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('block.banner_title')->addTag('ui.block', [
            'kind' => 'banner_title',
            'label' => 'label.banner_title',
            'form' => 'App\\Form\\BannerTitleType',
            'template' => 'banner_title.html.twig',
            'media_required' => 'true',
        ]);

        new BlockRegistryPass()->process($container);

        $calls = $container->getDefinition(BlockRegistry::class)->getMethodCalls();
        $this->assertTrue($calls[0][1][12]);
    }

    // media_multi_upload defaults to false and can be explicitly enabled, same boolean-flag parsing as media_required
    public function testProcessParsesMediaMultiUploadFlag(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('block.slider')->addTag('ui.block', [
            'kind' => 'slider',
            'label' => 'label.slider',
            'form' => 'App\\Form\\SliderType',
            'template' => 'slider.html.twig',
            'media_multi_upload' => 'true',
        ]);

        new BlockRegistryPass()->process($container);

        $calls = $container->getDefinition(BlockRegistry::class)->getMethodCalls();
        $this->assertTrue($calls[0][1][13]);
    }

    // media_types is a comma-separated string in the tag and must be split/trimmed into an array
    public function testProcessSplitsAndTrimsMediaTypes(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('block.slider')->addTag('ui.block', [
            'kind' => 'slider',
            'label' => 'label.slider',
            'form' => 'App\\Form\\SliderType',
            'template' => 'slider.html.twig',
            'media_types' => 'image/jpeg, image/png,image/webp',
        ]);

        new BlockRegistryPass()->process($container);

        $calls = $container->getDefinition(BlockRegistry::class)->getMethodCalls();
        $this->assertSame(['image/jpeg', 'image/png', 'image/webp'], $calls[0][1][5]);
    }

    // pickable/cacheable default to true unless explicitly disabled
    public function testProcessParsesPickableAndCacheableFlags(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('block.social_links')->addTag('ui.block', [
            'kind' => 'social_links',
            'label' => 'label.social_links',
            'form' => 'App\\Form\\SocialLinksType',
            'template' => 'social_links.html.twig',
            'pickable' => 'false',
            'cacheable' => 'false',
        ]);

        new BlockRegistryPass()->process($container);

        $calls = $container->getDefinition(BlockRegistry::class)->getMethodCalls();
        $this->assertFalse($calls[0][1][8]);
        $this->assertFalse($calls[0][1][10]);
    }

    // contexts is a comma-separated string in the tag and must be split/trimmed into an array, same as media_types
    public function testProcessSplitsAndTrimsContexts(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('block.link')->addTag('ui.block', [
            'kind' => 'link',
            'label' => 'label.link',
            'form' => 'App\\Form\\LinkType',
            'template' => 'link.html.twig',
            'contexts' => 'menu, sidebar',
        ]);

        new BlockRegistryPass()->process($container);

        $calls = $container->getDefinition(BlockRegistry::class)->getMethodCalls();
        $this->assertSame(['menu', 'sidebar'], $calls[0][1][11]);
    }

    // container defaults to false and can be explicitly enabled, same boolean-flag parsing as pickable/cacheable
    public function testProcessParsesContainerFlag(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('block.flex_columns')->addTag('ui.block', [
            'kind' => 'flex_columns',
            'label' => 'label.flex_columns',
            'form' => 'App\\Form\\FlexColumnsType',
            'template' => 'flex_columns.html.twig',
            'container' => 'true',
        ]);

        new BlockRegistryPass()->process($container);

        $calls = $container->getDefinition(BlockRegistry::class)->getMethodCalls();
        $this->assertTrue($calls[0][1][15]);
    }

    // slot_context defaults to BlockRegistry::SLOT_CONTEXT when not declared
    public function testProcessDefaultsSlotContextToSlotContextConstant(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('block.flex_columns')->addTag('ui.block', [
            'kind' => 'flex_columns',
            'label' => 'label.flex_columns',
            'form' => 'App\\Form\\FlexColumnsType',
            'template' => 'flex_columns.html.twig',
            'container' => 'true',
        ]);

        new BlockRegistryPass()->process($container);

        $calls = $container->getDefinition(BlockRegistry::class)->getMethodCalls();
        $this->assertSame(BlockRegistry::SLOT_CONTEXT, $calls[0][1][16]);
    }

    // A nested container (e.g. "flex_column") overrides slot_context so its own slots use a distinct context
    public function testProcessParsesSlotContextAttribute(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('block.flex_column')->addTag('ui.block', [
            'kind' => 'flex_column',
            'label' => 'label.flex_column',
            'form' => 'App\\Form\\FlexColumnType',
            'template' => 'flex_column.html.twig',
            'container' => 'true',
            'slot_context' => BlockRegistry::NESTED_SLOT_CONTEXT,
        ]);

        new BlockRegistryPass()->process($container);

        $calls = $container->getDefinition(BlockRegistry::class)->getMethodCalls();
        $this->assertSame(BlockRegistry::NESTED_SLOT_CONTEXT, $calls[0][1][16]);
    }

    // media_help defaults to an empty string (BlockRegistry::getMediaHelp() falls back to the generic label itself) and can be explicitly declared, as "document_download" does
    public function testProcessParsesMediaHelpAttribute(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('block.document_download')->addTag('ui.block', [
            'kind' => 'document_download',
            'label' => 'label.document_download',
            'form' => 'App\\Form\\DocumentDownloadType',
            'template' => 'document_download.html.twig',
            'media_help' => 'label.document_download_media_help',
        ]);

        new BlockRegistryPass()->process($container);

        $calls = $container->getDefinition(BlockRegistry::class)->getMethodCalls();
        $this->assertSame('label.document_download_media_help', $calls[0][1][17]);
    }

    public function testProcessThrowsWhenARequiredAttributeIsMissing(): void
    {
        $container = new ContainerBuilder();
        $container->register(BlockRegistry::class);
        $container->register('block.broken')->addTag('ui.block', [
            'kind' => 'broken',
            'label' => 'label.broken',
            'form' => 'App\\Form\\BrokenType',
            // "template" missing
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('block.broken');

        new BlockRegistryPass()->process($container);
    }
}
