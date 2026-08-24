<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\CollectionType;
use c975L\UiBundle\Registry\CollectionSourceRegistry;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

class CollectionTypeTest extends TestCase
{
    private function buildAddedFields(CollectionSourceRegistry $sourceRegistry): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new CollectionType($sourceRegistry, new BlockAnchorSlugger(new AsciiSlugger()))->buildForm($builder, []);

        return $added;
    }

    // Captures the PRE_SET_DATA listener and fires it with the block's stored data, returning the options the "limit" field is re-added with - null when it is left as built
    private function firePreSetData(CollectionSourceRegistry $sourceRegistry, mixed $data): ?array
    {
        $listener = null;
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $builder->method('addEventListener')->willReturnCallback(
            function (string $eventName, callable $callback) use (&$listener, $builder) {
                $listener = $callback;

                return $builder;
            }
        );

        new CollectionType($sourceRegistry, new BlockAnchorSlugger(new AsciiSlugger()))->buildForm($builder, []);

        $readded = null;
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$readded, $form) {
            $readded = $options;

            return $form;
        });

        $listener(new PreSetDataEvent($form, $data));

        return $readded;
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields(new CollectionSourceRegistry());

        foreach (['source', 'limit', 'title', 'anchor', 'variant'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the Collection form");
        }
    }

    public function testVariantChoicesOfferCardCompactAndPortfolio(): void
    {
        $added = $this->buildAddedFields(new CollectionSourceRegistry());

        $this->assertSame([
            'label.variant_card' => '',
            'label.variant_compact' => 'compact',
            'label.variant_portfolio' => 'portfolio',
        ], $added['variant']['choices']);
    }

    // The header fields are optional - only "source" (which collection to pull from) is required
    public function testOnlySourceIsRequired(): void
    {
        $added = $this->buildAddedFields(new CollectionSourceRegistry());

        foreach (['limit', 'title'] as $field) {
            $this->assertFalse($added[$field]['required'], "\"$field\" should not be required");
        }
    }

    public function testSourceChoicesComeFromTheSourceRegistry(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('choices')->willReturn(['Projects' => 'site.collection.projects']);

        $added = $this->buildAddedFields($sourceRegistry);

        $this->assertSame(['Projects' => 'site.collection.projects'], $added['source']['choices']);
    }

    public function testSourcePlaceholderExplainsWhenNoProviderIsRegistered(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('choices')->willReturn([]);

        $added = $this->buildAddedFields($sourceRegistry);

        $this->assertSame('label.no_collection_source_available', $added['source']['placeholder']);
    }

    public function testSourceHasNoPlaceholderWhenSourcesAreAvailable(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('choices')->willReturn(['Projects' => 'site.collection.projects']);

        $added = $this->buildAddedFields($sourceRegistry);

        $this->assertNull($added['source']['placeholder']);
    }

    // "Leave empty to show everything" says nothing about how many that is - a source able to answer its own total puts it in that very help
    public function testTheLimitHelpCarriesTheSourcesTotalWhenItDeclaresOne(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('count')->willReturn(128);

        $options = $this->firePreSetData($sourceRegistry, ['source' => 'book.all']);

        $this->assertSame('label.collection_limit_help_total', $options['help']);
        $this->assertSame(['%total%' => 128], $options['help_translation_parameters']);
    }

    // Every source that declares no total, and a block whose source is not picked yet: the field stays exactly the one built above
    public function testTheLimitFieldIsLeftUntouchedWhenNoTotalIsAvailable(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('count')->willReturn(null);

        $this->assertNull($this->firePreSetData($sourceRegistry, ['source' => 'site.collection.projects']));
        $this->assertNull($this->firePreSetData($sourceRegistry, null));

        $added = $this->buildAddedFields(new CollectionSourceRegistry());
        $this->assertSame('label.collection_limit_help', $added['limit']['help']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new CollectionType(new CollectionSourceRegistry(), new BlockAnchorSlugger(new AsciiSlugger()));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
