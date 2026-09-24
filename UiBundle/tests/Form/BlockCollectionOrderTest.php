<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Form\AnimationChoiceType;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Registry\BlockRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// A card dragged elsewhere in a grid keeps the index its field names carry, so the browser posts it at its new place under its old number: CollectionType binding by index put every card straight back where it was, and the new order was lost on save without a word
class BlockCollectionOrderTest extends TestCase
{
    public function testTheCardsAreSavedInTheOrderTheyWerePosted(): void
    {
        $block = new Block()->setKind('section_features')->setData(['cards' => [['title' => 'First'], ['title' => 'Second']]]);

        $this->submit($block, [
            'kind' => 'section_features',
            'data' => ['cards' => [1 => ['title' => 'Second'], 0 => ['title' => 'First']]],
        ]);

        $this->assertSame([['title' => 'Second'], ['title' => 'First']], $block->getData()['cards']);
    }

    // A card taken out leaves a gap in the numbering, closed rather than kept
    public function testARemovedCardLeavesNoGap(): void
    {
        $block = new Block()->setKind('section_features')->setData(['cards' => [['title' => 'First'], ['title' => 'Second'], ['title' => 'Third']]]);

        $this->submit($block, [
            'kind' => 'section_features',
            'data' => ['cards' => [2 => ['title' => 'Third'], 0 => ['title' => 'First']]],
        ]);

        $this->assertSame([['title' => 'Third'], ['title' => 'First']], $block->getData()['cards']);
    }

    private function submit(Block $block, array $submitted): void
    {
        $type = new BlockType($this->registry(), $this->router(), new RequestStack([new Request(request: ['kind' => 'section_features'])]));
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([$type, new AnimationChoiceType()], []))
            ->getFormFactory();

        $form = $factory->create(BlockType::class, $block, ['context' => null]);
        $form->submit($submitted, false);

        $this->assertTrue($form->isSynchronized());
    }

    private function registry(): BlockRegistry
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('isContainer')->willReturn(false);
        $registry->method('hasMediaTypes')->willReturn(false);
        $registry->method('getFormClass')->willReturn(CardsDataType::class);
        $registry->method('getTranslatableCollections')->willReturn([]);
        $registry->method('isAllowedInContext')->willReturn(true);
        $registry->method('groupedByCategory')->willReturn(['Sections' => ['Features' => 'section_features']]);

        return $registry;
    }

    private function router(): UrlGeneratorInterface
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/management/ui/block/data-form');

        return $router;
    }
}

// Stands in for SectionFeaturesType: a kind's data holding one collection of plain rows
class CardsDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('cards', CollectionType::class, [
            'entry_type' => CardEntryType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
        ]);
    }
}

class CardEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class);
    }
}
