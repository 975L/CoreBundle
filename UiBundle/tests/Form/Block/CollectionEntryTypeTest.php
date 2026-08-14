<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\CollectionEntryType;
use c975L\UiBundle\Registry\CollectionSourceRegistry;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

class CollectionEntryTypeTest extends TestCase
{
    private function buildAddedFields(CollectionSourceRegistry $sourceRegistry): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new CollectionEntryType($sourceRegistry, new BlockAnchorSlugger(new AsciiSlugger()))->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields(new CollectionSourceRegistry());

        foreach (['source', 'pick', 'slug', 'title', 'anchor'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the CollectionEntry form");
        }
    }

    public function testThePickOffersTheThreeWaysOfNamingAnItem(): void
    {
        $added = $this->buildAddedFields(new CollectionSourceRegistry());

        $this->assertSame([
            'label.collection_entry_pick_first' => 'first',
            'label.collection_entry_pick_last' => 'last',
            'label.collection_entry_pick_slug' => 'slug',
        ], $added['pick']['choices']);
    }

    // No placeholder, same as every other stored-choice field of this bundle: an unset value has to keep meaning "first"
    public function testThePickOffersNoEmptyChoice(): void
    {
        $added = $this->buildAddedFields(new CollectionSourceRegistry());

        $this->assertFalse($added['pick']['placeholder']);
    }

    // Only "source" and "pick" answer for what is shown; the slug is needed by one pick out of three, and the head is decoration
    public function testOnlyTheSourceAndThePickAreRequired(): void
    {
        $added = $this->buildAddedFields(new CollectionSourceRegistry());

        foreach (['slug', 'title', 'eyebrow', 'detailPage'] as $field) {
            $this->assertFalse($added[$field]['required'], "\"$field\" should not be required");
        }
    }

    public function testSourceChoicesComeFromTheSourceRegistry(): void
    {
        $sourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $sourceRegistry->method('choices')->willReturn(['Albums' => 'guild.albums']);

        $added = $this->buildAddedFields($sourceRegistry);

        $this->assertSame(['Albums' => 'guild.albums'], $added['source']['choices']);
        $this->assertNull($added['source']['placeholder']);
    }

    // A fresh install with no provider at all: the select says why it is empty instead of leaving the editor guessing
    public function testAnEmptyRegistryExplainsItselfInThePlaceholder(): void
    {
        $added = $this->buildAddedFields(new CollectionSourceRegistry());

        $this->assertSame('label.no_collection_source_available', $added['source']['placeholder']);
    }

    // Same reason as CollectionType's own: the prefix derived from the class name would collide with Symfony's CollectionType inside PageCrudController's "blocks" field
    public function testTheBlockPrefixIsNamespaced(): void
    {
        $this->assertSame(
            'block_collection_entry',
            new CollectionEntryType(new CollectionSourceRegistry(), new BlockAnchorSlugger(new AsciiSlugger()))->getBlockPrefix()
        );
    }
}
