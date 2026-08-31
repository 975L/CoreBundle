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
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\TranslationFormContext;
use c975L\UiBundle\Service\VideoPosterImporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Count;

class BlockTypeTest extends TestCase
{
    private function createRouter(): UrlGeneratorInterface
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/ui/block/data-form');

        return $router;
    }

    // Captures every builder->add() call's options, so the "kind" field's "choices" can be asserted
    private function buildAddedOptions(BlockType $type, array $options): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $builder) {
            $added[$name] = $fieldOptions;

            return $builder;
        });
        $builder->method('addEventListener')->willReturnSelf();

        // Every caller here is about the "kind" field, so the option each of them leaves out defaults the way the resolver would
        $type->buildForm($builder, $options + ['translation_locale' => null]);

        return $added;
    }

    public function testConfigureOptionsDefaultsContextToNull(): void
    {
        $type = new BlockType($this->createStub(BlockRegistry::class), $this->createRouter());
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['context']);
    }

    // "kind" field's choices are restricted per context (e.g. a CollectionField configured with context: 'menu' only offers kinds available in that context) - see BlockRegistry::groupedByCategory()
    public function testBuildFormPassesTheContextOptionToGroupedByCategory(): void
    {
        $registry = $this->createMock(BlockRegistry::class);
        $registry->expects($this->once())
            ->method('groupedByCategory')
            ->with('menu')
            ->willReturn(['Navigation' => ['Menu link' => 'menu_link']]);

        $type = new BlockType($registry, $this->createRouter());
        $added = $this->buildAddedOptions($type, ['context' => 'menu']);

        $this->assertSame(['Navigation' => ['Menu link' => 'menu_link']], $added['kind']['choices']);
    }

    // The choice label a bare <select> shows is the label and the description run together; the visual picker gives them a line each and reads them from these attributes, or it would print the description twice over (see block-picker.js)
    public function testEachOptionCarriesItsLabelAndDescriptionApart(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('groupedByCategory')->willReturn(['Media' => ['Image (Image seule)' => 'image']]);
        $registry->method('getLabel')->willReturn('Image');
        $registry->method('getDescription')->willReturn('Image seule');

        $type = new BlockType($registry, $this->createRouter());
        $added = $this->buildAddedOptions($type, ['context' => null]);

        $this->assertSame(
            ['data-label' => 'Image', 'data-description' => 'Image seule'],
            $added['kind']['choice_attr']('image')
        );
    }

    // A CollectionField that never sets "context" (existing usages, before this option existed) must keep seeing every pickable kind - groupedByCategory(null) applies no context filter
    public function testBuildFormWithNoContextPassesNullToGroupedByCategory(): void
    {
        $registry = $this->createMock(BlockRegistry::class);
        $registry->expects($this->once())->method('groupedByCategory')->with(null)->willReturn([]);

        $type = new BlockType($registry, $this->createRouter());
        $this->buildAddedOptions($type, ['context' => null]);
    }

    private function invokeMergeMultiUpload(BlockType $type, array $submitted, string $kind): array
    {
        return new \ReflectionMethod($type, 'mergeMultiUpload')->invoke($type, $submitted, $kind);
    }

    // Captures every form->add() call's options fired by the private addMediaSubForm(), so the "medias" field's "constraints" can be asserted
    private function buildAddedMediaOptions(BlockType $type, string $kind): array
    {
        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $form) {
            $added[$name] = $fieldOptions;

            return $form;
        });

        new \ReflectionMethod($type, 'addMediaSubForm')->invoke($type, $form, $kind);

        return $added;
    }

    private function createUploadedFile(string $originalName): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ui-block-type-test-');

        return new UploadedFile($path, $originalName, null, null, true);
    }

    public function testMergeMultiUploadAlwaysRemovesTheMediaUploadKey(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('allowsMultiUpload')->willReturn(false);
        $type = new BlockType($registry, $this->createRouter());

        $result = $this->invokeMergeMultiUpload($type, ['medias' => [], 'mediaUpload' => null], 'article');

        $this->assertArrayNotHasKey('mediaUpload', $result);
    }

    public function testMergeMultiUploadLeavesMediasUnchangedWhenKindDoesNotAllowIt(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('allowsMultiUpload')->willReturn(false);
        $type = new BlockType($registry, $this->createRouter());

        $file = $this->createUploadedFile('a.jpg');
        $result = $this->invokeMergeMultiUpload($type, ['medias' => ['x'], 'mediaUpload' => [$file]], 'article');

        $this->assertSame(['x'], $result['medias']);
    }

    public function testMergeMultiUploadLeavesMediasUnchangedWhenNoFilesWereSubmitted(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('allowsMultiUpload')->willReturn(true);
        $type = new BlockType($registry, $this->createRouter());

        $result = $this->invokeMergeMultiUpload($type, ['medias' => ['x']], 'slider');

        $this->assertSame(['x'], $result['medias']);
    }

    // The actual splicing logic is MultiUploadMerger's own (see MultiUploadMergerTest) - this only verifies BlockType wires it in correctly for a kind that allows multi upload
    public function testMergeMultiUploadSplicesSubmittedFilesIntoMediasWhenKindAllowsIt(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('allowsMultiUpload')->willReturn(true);
        $type = new BlockType($registry, $this->createRouter());

        $file = $this->createUploadedFile('a.jpg');
        $result = $this->invokeMergeMultiUpload($type, ['medias' => [], 'mediaUpload' => [$file]], 'slider');

        $this->assertArrayNotHasKey('mediaUpload', $result);
        $this->assertCount(1, $result['medias']);
        $this->assertSame($file, $result['medias'][0]['file']['file']);
    }

    // "hero"'s pure-CSS crossfade only has slide rules for up to 6 images (see sass/_page-sections.scss) - this caps the field so an editor can't silently attach a 7th that would collide with an earlier slide
    public function testAddMediaSubFormCapsHeroMediaCountWithACountConstraint(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getMediaTypes')->willReturn(['image/*']);
        $registry->method('allowsMultiUpload')->willReturn(true);
        $type = new BlockType($registry, $this->createRouter());

        $added = $this->buildAddedMediaOptions($type, 'hero');

        $this->assertCount(1, $added['medias']['constraints']);
        $this->assertInstanceOf(Count::class, $added['medias']['constraints'][0]);
        $this->assertSame(9, $added['medias']['constraints'][0]->max);
    }

    public function testAddMediaSubFormAddsNoConstraintsForKindsOtherThanHero(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getMediaTypes')->willReturn(['image/*']);
        $registry->method('allowsMultiUpload')->willReturn(false);
        $type = new BlockType($registry, $this->createRouter());

        $added = $this->buildAddedMediaOptions($type, 'article');

        $this->assertSame([], $added['medias']['constraints']);
    }

    // The "medias" field's help text is whatever BlockRegistry::getMediaHelp() declares for the kind (see BlockRegistryTest for the kind-specific-vs-generic logic itself) - addMediaSubForm() just wires it through
    public function testAddMediaSubFormUsesTheRegistrysDeclaredMediaHelpText(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getMediaTypes')->willReturn(['application/pdf']);
        $registry->method('getMediaHelp')->willReturn('label.document_download_media_help');
        $type = new BlockType($registry, $this->createRouter());

        $added = $this->buildAddedMediaOptions($type, 'document_download');

        $this->assertSame('label.document_download_media_help', $added['medias']['help']);
    }

    // A container's "slots" is a CollectionType of BlockType itself, scoped to that kind's slot context
    public function testAddSlotsSubFormAddsACollectionOfBlockTypeScopedToTheKindsOwnSlotContext(): void
    {
        $registry = $this->createMock(BlockRegistry::class);
        $registry->expects($this->once())->method('getSlotContext')->with('flex_columns')->willReturn(BlockRegistry::SLOT_CONTEXT);
        $type = new BlockType($registry, $this->createRouter());

        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $form) {
            $added[$name] = ['type' => $fieldType, 'options' => $fieldOptions];

            return $form;
        });

        new \ReflectionMethod($type, 'addSlotsSubForm')->invoke($type, $form, 'flex_columns', null);

        $this->assertSame(CollectionType::class, $added['slots']['type']);
        $this->assertSame(BlockType::class, $added['slots']['options']['entry_type']);
        $this->assertSame(BlockRegistry::SLOT_CONTEXT, $added['slots']['options']['entry_options']['context']);
        $this->assertTrue($added['slots']['options']['allow_add']);
        $this->assertTrue($added['slots']['options']['allow_delete']);
        $this->assertSame('label.slots', $added['slots']['options']['label']);
    }

    // "section_cards" gets its own label, the two containers sharing the mechanism but not the wording
    public function testAddSlotsSubFormUsesADedicatedLabelForSectionCards(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getSlotContext')->willReturn(BlockRegistry::SLOT_CONTEXT);
        $type = new BlockType($registry, $this->createRouter());

        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $form) {
            $added[$name] = ['type' => $fieldType, 'options' => $fieldOptions];

            return $form;
        });

        new \ReflectionMethod($type, 'addSlotsSubForm')->invoke($type, $form, 'section_cards', null);

        $this->assertSame('label.slots_cards', $added['slots']['options']['label']);
    }

    // No container id yet, so "slots" must not be marked a Block collection, and getData() must not be called
    public function testAddSlotsSubFormOmitsRowAttrWhenTheContainerIsNotYetPersisted(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getSlotContext')->willReturn(BlockRegistry::SLOT_CONTEXT);
        $type = new BlockType($registry, $this->createRouter());

        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $form) {
            $added[$name] = ['type' => $fieldType, 'options' => $fieldOptions];

            return $form;
        });

        new \ReflectionMethod($type, 'addSlotsSubForm')->invoke($type, $form, 'flex_columns', null);

        $this->assertSame([], $added['slots']['options']['row_attr']);
    }

    // A persisted container marks "slots" as a Block collection carrying its own id
    public function testAddSlotsSubFormAddsRowAttrWithTheContainersOwnIdWhenPersisted(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getSlotContext')->willReturn(BlockRegistry::SLOT_CONTEXT);
        $type = new BlockType($registry, $this->createRouter());

        $container = new Block();
        new \ReflectionProperty(Block::class, 'id')->setValue($container, 42);

        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $form) {
            $added[$name] = ['type' => $fieldType, 'options' => $fieldOptions];

            return $form;
        });

        new \ReflectionMethod($type, 'addSlotsSubForm')->invoke($type, $form, 'flex_columns', $container);

        $this->assertSame('block', $added['slots']['options']['row_attr']['data-ui-sort-group']);
        $this->assertSame(42, $added['slots']['options']['row_attr']['data-ui-move-target']);
    }

    // A nested container declares its slots with its own context, so no column can hold a column
    public function testAddSlotsSubFormUsesTheKindsDeclaredSlotContext(): void
    {
        $registry = $this->createMock(BlockRegistry::class);
        $registry->expects($this->once())->method('getSlotContext')->with('flex_column')->willReturn(BlockRegistry::NESTED_SLOT_CONTEXT);
        $type = new BlockType($registry, $this->createRouter());

        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $form) {
            $added[$name] = ['type' => $fieldType, 'options' => $fieldOptions];

            return $form;
        });

        new \ReflectionMethod($type, 'addSlotsSubForm')->invoke($type, $form, 'flex_column', null);

        $this->assertSame(BlockRegistry::NESTED_SLOT_CONTEXT, $added['slots']['options']['entry_options']['context']);
    }

    // The same legacy kinds, listed on the container's field, the only help visible without expanding
    public function testAddSlotsSubFormWarnsAboutTheSlotsHoldingAKindTheContextNoLongerOffers(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getSlotContext')->willReturn(BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT);
        $registry->method('has')->willReturn(true);
        $registry->method('isAllowedInContext')->willReturnCallback(fn (string $kind) => 'flex_column' === $kind);

        $added = $this->invokeAddSlotsSubForm($registry, $this->createContainer([
            ['kind' => 'text_section', 'position' => 0, 'title' => 'Le manifeste'],
            ['kind' => 'flex_column', 'position' => 1, 'title' => null],
        ]));

        $this->assertSame('label.slots_legacy_kinds_help', $added['slots']['options']['help']);
        $this->assertSame(
            ['%blocks%' => '(#0) Text_section - Le manifeste'],
            $added['slots']['options']['help_translation_parameters']
        );
    }

    // A conforming container has nothing to warn about - no help at all, rather than an empty warning
    public function testAddSlotsSubFormAddsNoWarningWhenEverySlotIsAllowedInTheContext(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getSlotContext')->willReturn(BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT);
        $registry->method('has')->willReturn(true);
        $registry->method('isAllowedInContext')->willReturn(true);

        $added = $this->invokeAddSlotsSubForm($registry, $this->createContainer([
            ['kind' => 'flex_column', 'position' => 0, 'title' => null],
        ]));

        $this->assertNull($added['slots']['options']['help']);
    }

    // The warning is HTML and names each slot, so editor-provided titles must not reach the page as markup
    public function testAddSlotsSubFormEscapesTheSlotTitlesItListsInTheWarning(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getSlotContext')->willReturn(BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT);
        $registry->method('has')->willReturn(true);
        $registry->method('isAllowedInContext')->willReturn(false);

        $added = $this->invokeAddSlotsSubForm($registry, $this->createContainer([
            ['kind' => 'text_section', 'position' => 0, 'title' => '<script>alert(1)</script>'],
        ]));

        $this->assertTrue($added['slots']['options']['help_html']);
        $this->assertSame(
            ['%blocks%' => '(#0) Text_section - &lt;script&gt;alert(1)&lt;/script&gt;'],
            $added['slots']['options']['help_translation_parameters']
        );
    }

    // A persisted container holding the given slots, as addSlotsSubForm() receives it from BlockType's own PRE_SET_DATA
    private function createContainer(array $slots): Block
    {
        $container = new Block();
        new \ReflectionProperty(Block::class, 'id')->setValue($container, 42);

        foreach ($slots as $definition) {
            $slot = new Block()
                ->setKind($definition['kind'])
                ->setPosition($definition['position']);
            if (null !== $definition['title']) {
                $slot->setData(['title' => $definition['title']]);
            }
            $container->addSlot($slot);
        }

        return $container;
    }

    // Captures the "slots" field the private addSlotsSubForm() adds for a "flex_columns" container
    private function invokeAddSlotsSubForm(BlockRegistry $registry, ?Block $container): array
    {
        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $form) {
            $added[$name] = ['type' => $fieldType, 'options' => $fieldOptions];

            return $form;
        });

        $type = new BlockType($registry, $this->createRouter());
        new \ReflectionMethod($type, 'addSlotsSubForm')->invoke($type, $form, 'flex_columns', $container);

        return $added;
    }

    // A kind its context no longer lists is put back for that one form, else the editor is locked out
    public function testAKindTheContextNoLongerOffersIsPutBackForTheBlockAlreadyHoldingIt(): void
    {
        $registry = $this->createMock(BlockRegistry::class);
        $registry->method('groupedByCategory')->willReturn(['Sections' => ['Column' => 'flex_column']]);
        $registry->expects($this->once())->method('isAllowedInContext')->with('text_section', BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT)->willReturn(false);
        $registry->method('getCategory')->willReturn('Sections');
        $registry->method('getLabel')->willReturn('Text section');

        $added = $this->invokeAddKindField($registry, BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT, 'text_section');

        $this->assertSame(['Column' => 'flex_column', 'Text section' => 'text_section'], $added['kind']['choices']['Sections']);
        $this->assertSame('label.block_kind_legacy_slot_help', $added['kind']['help']);
    }

    // Every other slot's list is left as the context built it, with no warning where there is nothing to warn
    public function testAKindTheContextStillOffersIsLeftAloneAndCarriesNoWarning(): void
    {
        $registry = $this->createMock(BlockRegistry::class);
        $registry->method('groupedByCategory')->willReturn(['Sections' => ['Column' => 'flex_column']]);
        $registry->method('isAllowedInContext')->willReturn(true);
        $registry->expects($this->never())->method('getLabel');

        $added = $this->invokeAddKindField($registry, BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT, 'flex_column');

        $this->assertSame(['Column' => 'flex_column'], $added['kind']['choices']['Sections']);
        $this->assertNull($added['kind']['help']);
    }

    // A slot being added holds no kind yet, so there is nothing to put back: it sees the restricted list
    public function testANewlyAddedSlotIsNeverCheckedForALegacyKind(): void
    {
        $registry = $this->createMock(BlockRegistry::class);
        $registry->method('groupedByCategory')->willReturn(['Sections' => ['Column' => 'flex_column']]);
        $registry->expects($this->never())->method('isAllowedInContext');

        $added = $this->invokeAddKindField($registry, BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT, null);

        $this->assertSame(['Column' => 'flex_column'], $added['kind']['choices']['Sections']);
    }

    // The import sets a Vich file field, which has to be in place before Vich's own prePersist/preUpdate listener runs - hence the form's POST_SUBMIT rather than a Doctrine listener
    public function testPostSubmitHandsTheSubmittedBlockToThePosterImporter(): void
    {
        $block = new Block();
        $importer = $this->createMock(VideoPosterImporter::class);
        $importer->expects($this->once())->method('importIfRequested')->with($block);

        $this->dispatchPostSubmit($importer, $block);
    }

    // Every other POST_SUBMIT payload (a container's slot collection, say) is left alone
    public function testPostSubmitIgnoresDataThatIsNotABlock(): void
    {
        $importer = $this->createMock(VideoPosterImporter::class);
        $importer->expects($this->never())->method('importIfRequested');

        $this->dispatchPostSubmit($importer, null);
    }

    // Fires the POST_SUBMIT listener buildForm() registers, with the given submitted data
    private function dispatchPostSubmit(VideoPosterImporter $importer, mixed $data): void
    {
        $listeners = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $builder->method('addEventListener')->willReturnCallback(function (string $event, callable $listener) use (&$listeners, $builder) {
            $listeners[$event][] = $listener;

            return $builder;
        });

        $type = new BlockType($this->createStub(BlockRegistry::class), $this->createRouter(), null, $importer);
        $type->buildForm($builder, ['context' => null, 'translation_locale' => null]);

        $this->assertArrayHasKey(FormEvents::POST_SUBMIT, $listeners);

        foreach ($listeners[FormEvents::POST_SUBMIT] as $listener) {
            $listener(new PostSubmitEvent($this->createStub(FormInterface::class), $data));
        }
    }

    // Captures the "kind" field the private addKindField() adds, for a given context and already-held kind
    private function invokeAddKindField(BlockRegistry $registry, ?string $context, ?string $kind): array
    {
        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $form) {
            $added[$name] = $fieldOptions;

            return $form;
        });

        $type = new BlockType($registry, $this->createRouter());
        new \ReflectionMethod($type, 'addKindField')->invoke($type, $form, $context, $kind);

        return $added;
    }

    // The same fields, in the language being written: what a language has to say, or the source text between brackets when it has said nothing yet - both the thing to translate and the mark of a field nobody has
    public function testTranslationModeOffersTheSourceTextBetweenBracketsUntilSomethingIsWritten(): void
    {
        $block = $this->createBlockWithId(7, ['title' => 'Bonjour', 'content' => 'Bienvenue', 'anchor' => 'hello']);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getFormClass')->willReturn(CollectionType::class);
        $registry->method('getTranslatable')->willReturn(['title', 'content']);

        $translator = $this->createStub(ContentTranslator::class);
        $translator->method('values')->willReturn(['title' => 'Hola']);

        $added = $this->invokeAddDataSubForm($registry, $translator, $block, 'es');

        $this->assertFalse($added['mapped'], 'Mapped back, what is written in another language would overwrite the text the site was written in.');
        $this->assertSame('Hola', $added['data']['title']);
        $this->assertSame('[Bienvenue]', $added['data']['content']);
    }

    // A colour, a link target or an image has one value for the whole site: rendered on a language screen, it would invite an editor to set it per language, which nothing would then read
    public function testTranslationModeRendersOnlyTheFieldsALanguageCanChange(): void
    {
        $block = $this->createBlockWithId(7, ['title' => 'Bonjour', 'anchor' => 'hello']);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getFormClass')->willReturn(CollectionType::class);
        $registry->method('getTranslatable')->willReturn(['title']);

        $translator = $this->createStub(ContentTranslator::class);
        $translator->method('values')->willReturn([]);

        $removed = [];
        $this->invokeAddDataSubForm($registry, $translator, $block, 'es', $removed);

        $this->assertSame(['anchor'], $removed);
    }

    // Left as it was offered, the bracketed source is a prompt to translate rather than a translation: stored, it would come out on the front reading "[Bonjour]"
    public function testAFieldLeftHoldingTheBracketedSourceIsStagedAsNothing(): void
    {
        $block = $this->createBlockWithId(7, ['title' => 'Bonjour', 'content' => 'Bienvenue']);

        $translator = $this->createMock(ContentTranslator::class);
        $translator->expects($this->once())
            ->method('stage')
            ->with('ui_block', 7, 'es', ['title' => null, 'content' => 'Bienvenido']);

        $this->dispatchTranslationPostSubmit($translator, $block, ['title' => '[Bonjour]', 'content' => 'Bienvenido']);
    }

    // A rich text field hands its html back re-serialised by its editor, so the string that came out is never the string that went in - the words are what is compared
    public function testABracketedSourceIsRecognisedThroughTheMarkupItComesBackIn(): void
    {
        $block = $this->createBlockWithId(7, ['content' => '<p>Bienvenue</p>']);

        $translator = $this->createMock(ContentTranslator::class);
        $translator->expects($this->once())->method('stage')->with('ui_block', 7, 'es', ['content' => null]);

        $this->dispatchTranslationPostSubmit($translator, $block, ['content' => '<div>[</div><p>Bienvenue</p><div>]</div>']);
    }

    private function createBlockWithId(int $id, array $data): Block
    {
        $block = new Block();
        $block->setKind('article');
        $block->setData($data);
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);

        return $block;
    }

    // Captures the options the private addDataSubForm() gives the "data" sub-form, and the children it takes off it
    private function invokeAddDataSubForm(BlockRegistry $registry, ContentTranslator $translator, Block $block, string $locale, array &$removed = []): array
    {
        $added = [];

        $data = $this->createStub(FormInterface::class);
        $data->method('all')->willReturn(array_fill_keys(array_keys($block->getData()), $this->createStub(FormInterface::class)));
        $data->method('remove')->willReturnCallback(function (string $name) use (&$removed, $data) {
            $removed[] = $name;

            return $data;
        });

        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $form) {
            $added = $options;

            return $form;
        });
        $form->method('get')->willReturn($data);

        $type = new BlockType($registry, $this->createRouter(), null, null, $translator);
        new \ReflectionMethod(BlockType::class, 'addDataSubForm')->invoke($type, $form, 'article', $block, $locale);

        return $added;
    }

    // Fires the POST_SUBMIT listener buildForm() registers in translation mode, with the given submitted values
    private function dispatchTranslationPostSubmit(ContentTranslator $translator, Block $block, array $submitted): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getTranslatable')->willReturn(array_keys($submitted));

        $listeners = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $builder->method('addEventListener')->willReturnCallback(function (string $event, callable $listener) use (&$listeners, $builder) {
            $listeners[$event][] = $listener;

            return $builder;
        });

        $type = new BlockType($registry, $this->createRouter(), null, null, $translator);
        $type->buildForm($builder, ['context' => null, 'translation_locale' => 'es']);

        $data = $this->createStub(FormInterface::class);
        $data->method('getData')->willReturn($submitted);

        $form = $this->createStub(FormInterface::class);
        $form->method('has')->willReturn(true);
        $form->method('get')->willReturn($data);

        foreach ($listeners[FormEvents::POST_SUBMIT] as $listener) {
            $listener(new PostSubmitEvent($form, $block));
        }
    }

    // A slot rendered without the language would edit itself mapped, and what an editor writes in another language would land on the text the site was written in - one level down from the block the screen was opened on
    public function testTranslationModeTravelsDownIntoASlotsOwnForm(): void
    {
        $added = $this->slotsCollectionOptionsFor('es');

        $this->assertSame('es', $added['entry_options']['translation_locale']);
        $this->assertFalse($added['allow_add'], 'A page is composed once, in the language it was written in.');
        $this->assertFalse($added['allow_delete'], 'A slot taken away from a language screen would be taken away from every language at once.');
    }

    // The no-regression contract: an ordinary edit screen keeps the collection it always had
    public function testASlotCollectionOutsideTranslationModeIsUntouched(): void
    {
        $added = $this->slotsCollectionOptionsFor(null);

        $this->assertNull($added['entry_options']['translation_locale']);
        $this->assertTrue($added['allow_add']);
        $this->assertTrue($added['allow_delete']);
    }

    // An image is the same image in every language, and re-adding its sub-form against a submission that never carried one has CollectionType read it as "every row removed"
    public function testTranslationModeRendersNoMediaAtAll(): void
    {
        $block = $this->createBlockWithId(7, ['title' => 'Bonjour']);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getFormClass')->willReturn(CollectionType::class);
        $registry->method('getTranslatable')->willReturn(['title']);
        $registry->method('hasMediaTypes')->willReturn(true);
        $registry->method('isContainer')->willReturn(false);
        $registry->method('groupedByCategory')->willReturn([]);

        $added = [];
        $data = $this->createStub(FormInterface::class);
        $data->method('all')->willReturn([]);
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $form) {
            $added[$name] = $fieldOptions;

            return $form;
        });
        $form->method('get')->willReturn($data);

        $translator = $this->createStub(ContentTranslator::class);
        $translator->method('values')->willReturn([]);

        $type = new BlockType($registry, $this->createRouter(), null, null, $translator);
        new \ReflectionMethod(BlockType::class, 'onPreSetData')->invoke($type, new PreSetDataEvent($form, $block), null, 'es');

        $this->assertArrayNotHasKey('medias', $added);
        $this->assertArrayHasKey('data', $added);
    }

    // Donovan's toolbar sits in each kind's own FormType, several levels below the sub-form this language applies to - too far for a template to walk up to, so the form says it once for the screen
    public function testTranslationModeTellsTheToolbarWhichLanguageItTranslatesInto(): void
    {
        $context = new TranslationFormContext();

        $this->buildFormWith($context, 'es');

        $this->assertSame('es', $context->get());
    }

    // The no-regression contract: an ordinary edit screen leaves the toolbar exactly as it was, offering styles rather than a language
    public function testAnOrdinaryScreenTellsTheToolbarNothing(): void
    {
        $context = new TranslationFormContext();

        $this->buildFormWith($context, null);

        $this->assertNull($context->get());
    }

    private function buildFormWith(TranslationFormContext $context, ?string $translationLocale): void
    {
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $builder->method('addEventListener')->willReturnSelf();

        new BlockType($this->createStub(BlockRegistry::class), $this->createRouter(), null, null, null, $context)
            ->buildForm($builder, ['context' => null, 'translation_locale' => $translationLocale]);
    }

    // The kind is what a block is, not what it says: switching it from a language screen would swap the fields of every language at once
    public function testTranslationModeOffersTheKindItAlreadyHoldsAndNoOther(): void
    {
        $added = $this->kindFieldOptionsFor(true);

        $this->assertSame(['Carte' => 'card'], $added['choices']);
        $this->assertArrayNotHasKey('data-controller', $added['attr'], 'The kind picker would otherwise swap the data sub-form over AJAX.');
    }

    // Offered rather than disabled: a disabled field is left out of the submission, and PRE_SUBMIT reads the kind to know which sub-form to put back
    public function testTheLockedKindIsStillSubmitted(): void
    {
        $this->assertArrayNotHasKey('disabled', $this->kindFieldOptionsFor(true));
    }

    // The no-regression contract: an ordinary edit screen keeps the whole palette
    public function testTheKindPickerOutsideTranslationModeKeepsEveryChoice(): void
    {
        $added = $this->kindFieldOptionsFor(false);

        $this->assertSame(['Sections' => ['Bannière' => 'banner']], $added['choices']);
        $this->assertArrayHasKey('data-controller', $added['attr']);
    }

    // The options the private addKindField() gives the "kind" field, locked or not
    private function kindFieldOptionsFor(bool $locked): array
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('groupedByCategory')->willReturn(['Sections' => ['Bannière' => 'banner']]);
        $registry->method('isAllowedInContext')->willReturn(true);
        $registry->method('getLabel')->willReturn('Carte');
        $registry->method('getDescription')->willReturn('');

        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $form) {
            $added[$name] = $fieldOptions;

            return $form;
        });

        $type = new BlockType($registry, $this->createRouter());
        new \ReflectionMethod(BlockType::class, 'addKindField')->invoke($type, $form, null, 'card', $locked);

        return $added['kind'];
    }

    // The options the "slots" collection is given for a language, through the same private method the edit screen goes through
    private function slotsCollectionOptionsFor(?string $translationLocale): array
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getSlotContext')->willReturn(BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT);

        $added = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $form) {
            $added[$name] = $fieldOptions;

            return $form;
        });

        $type = new BlockType($registry, $this->createRouter());
        new \ReflectionMethod(BlockType::class, 'addSlotsSubForm')->invoke($type, $form, 'flex_columns', null, $translationLocale);

        return $added['slots'];
    }
}
