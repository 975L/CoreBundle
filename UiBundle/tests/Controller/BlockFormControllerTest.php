<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Controller\BlockFormController;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Tests\Controller\Management\ControllerContainerTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class BlockFormControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    // Records every builder->add() call's field name, so the "mediaUpload" field's presence/absence can be asserted - createNamedBuilder()->add('data', ...) chains off the same stub
    private function createFormFactory(array &$added): FormFactoryInterface
    {
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name) use (&$added, $builder) {
            $added[] = $name;

            return $builder;
        });

        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $builder->method('getForm')->willReturn($form);

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('createNamedBuilder')->willReturn($builder);

        return $formFactory;
    }

    // Same as createFormFactory(), but records each builder->add() call's options too, so the "medias" field's "help" text can be asserted
    private function createFormFactoryCapturingOptions(array &$added): FormFactoryInterface
    {
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $fieldType = null, array $fieldOptions = []) use (&$added, $builder) {
            $added[$name] = $fieldOptions;

            return $builder;
        });

        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $builder->method('getForm')->willReturn($form);

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('createNamedBuilder')->willReturn($builder);

        return $formFactory;
    }

    // Same as createFormFactory(), but records the data createNamedBuilder() is handed, so what a duplication's posted values pre-fill the sub-form with can be asserted
    private function createFormFactoryCapturingInitialData(mixed &$initialData): FormFactoryInterface
    {
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturn($builder);

        $form = $this->createStub(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $builder->method('getForm')->willReturn($form);

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('createNamedBuilder')->willReturnCallback(
            function (string $name, string $type, mixed $data = null) use (&$initialData, $builder) {
                $initialData = $data;

                return $builder;
            }
        );

        return $formFactory;
    }

    private function createContainerWithTwig(): \Symfony\Component\DependencyInjection\Container
    {
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<form></form>');

        return $this->createContainer(['twig' => $twig]);
    }

    public function testDataFormReturnsNoContentWhenKindIsMissing(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $added = [];
        $controller = new BlockFormController($registry, $this->createFormFactory($added));

        $response = $controller->dataForm(new Request());

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testDataFormReturnsNoContentWhenKindIsUnknown(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(false);
        $added = [];
        $controller = new BlockFormController($registry, $this->createFormFactory($added));

        $response = $controller->dataForm(new Request(['k' => 'unknown']));

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testDataFormSkipsMediaCollectionWhenKindHasNoMediaTypes(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getFormClass')->willReturn(\Symfony\Component\Form\Extension\Core\Type\FormType::class);
        $registry->method('hasMediaTypes')->willReturn(false);
        $added = [];
        $controller = new BlockFormController($registry, $this->createFormFactory($added));
        $controller->setContainer($this->createContainerWithTwig());

        $controller->dataForm(new Request(['k' => 'text_section']));

        $this->assertNotContains('medias', $added);
        $this->assertNotContains('mediaUpload', $added);
    }

    // Mirrors BlockType::addMediaSubForm() - the AJAX-loaded kind preview must offer the same multi-upload input right away for a kind that allows it (e.g. "slider")
    public function testDataFormAddsMediaUploadFieldWhenKindAllowsMultiUpload(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getFormClass')->willReturn(\Symfony\Component\Form\Extension\Core\Type\FormType::class);
        $registry->method('hasMediaTypes')->willReturn(true);
        $registry->method('getMediaTypes')->willReturn(['image/*', 'video/*']);
        $registry->method('allowsMultiUpload')->willReturn(true);
        $added = [];
        $controller = new BlockFormController($registry, $this->createFormFactory($added));
        $controller->setContainer($this->createContainerWithTwig());

        $controller->dataForm(new Request(['k' => 'slider']));

        $this->assertContains('medias', $added);
        $this->assertContains('mediaUpload', $added);
    }

    public function testDataFormSkipsMediaUploadFieldWhenKindDoesNotAllowMultiUpload(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getFormClass')->willReturn(\Symfony\Component\Form\Extension\Core\Type\FormType::class);
        $registry->method('hasMediaTypes')->willReturn(true);
        $registry->method('getMediaTypes')->willReturn(['image/*']);
        $registry->method('allowsMultiUpload')->willReturn(false);
        $added = [];
        $controller = new BlockFormController($registry, $this->createFormFactory($added));
        $controller->setContainer($this->createContainerWithTwig());

        $controller->dataForm(new Request(['k' => 'image']));

        $this->assertContains('medias', $added);
        $this->assertNotContains('mediaUpload', $added);
    }

    // Mirrors BlockType::addSlotsSubForm() - picking a container kind (e.g. flex_columns) on a brand new block must show the "add a slot" collection right away, same as the medias case above
    public function testDataFormAddsSlotsFieldWhenKindIsContainer(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getFormClass')->willReturn(\Symfony\Component\Form\Extension\Core\Type\FormType::class);
        $registry->method('hasMediaTypes')->willReturn(false);
        $registry->method('isContainer')->willReturn(true);
        $registry->method('getSlotContext')->willReturn('flex_slot');
        $added = [];
        $controller = new BlockFormController($registry, $this->createFormFactory($added));
        $controller->setContainer($this->createContainerWithTwig());

        $controller->dataForm(new Request(['k' => 'flex_columns']));

        $this->assertContains('slots', $added);
    }

    public function testDataFormSkipsSlotsFieldWhenKindIsNotContainer(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getFormClass')->willReturn(\Symfony\Component\Form\Extension\Core\Type\FormType::class);
        $registry->method('hasMediaTypes')->willReturn(false);
        $registry->method('isContainer')->willReturn(false);
        $added = [];
        $controller = new BlockFormController($registry, $this->createFormFactory($added));
        $controller->setContainer($this->createContainerWithTwig());

        $controller->dataForm(new Request(['k' => 'text_section']));

        $this->assertNotContains('slots', $added);
    }

    // The "medias" field's help text is whatever BlockRegistry::getMediaHelp() declares for the kind (see BlockRegistryTest) - dataForm() just wires it through, same as BlockType::addMediaSubForm()
    public function testDataFormUsesTheRegistrysDeclaredMediaHelpText(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getFormClass')->willReturn(\Symfony\Component\Form\Extension\Core\Type\FormType::class);
        $registry->method('hasMediaTypes')->willReturn(true);
        $registry->method('getMediaTypes')->willReturn(['application/pdf']);
        $registry->method('getMediaHelp')->willReturn('label.document_download_media_help');
        $added = [];
        $controller = new BlockFormController($registry, $this->createFormFactoryCapturingOptions($added));
        $controller->setContainer($this->createContainerWithTwig());

        $controller->dataForm(new Request(['k' => 'document_download']));

        $this->assertSame('label.document_download_media_help', $added['medias']['help']);
    }

    // Duplicating a block (see block-duplicate.js) posts the source's own "data" values here so the sub-form comes back pre-filled rather than empty
    public function testDataFormPrefillsTheSubFormWithThePostedValues(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getFormClass')->willReturn(\Symfony\Component\Form\Extension\Core\Type\FormType::class);
        $registry->method('hasMediaTypes')->willReturn(false);
        $initialData = null;
        $controller = new BlockFormController($registry, $this->createFormFactoryCapturingInitialData($initialData));
        $controller->setContainer($this->createContainerWithTwig());

        $controller->dataForm(Request::create('/ui/block/data-form?k=slider', 'POST', [
            'data' => ['duration' => '5', 'ratio' => '16-9'],
        ]));

        $this->assertSame(['data' => ['duration' => '5', 'ratio' => '16-9']], $initialData);
    }

    // An id identifies the source block and it alone, whether auto-generated (SliderType/ImageCompareType) or an anchor typed by the editor (CardType/ReadmoreType) - carrying it over would leave two elements sharing an id in the page
    public function testDataFormDropsThePostedIdSoTheCopyGetsItsOwn(): void
    {
        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('getFormClass')->willReturn(\Symfony\Component\Form\Extension\Core\Type\FormType::class);
        $registry->method('hasMediaTypes')->willReturn(false);
        $initialData = null;
        $controller = new BlockFormController($registry, $this->createFormFactoryCapturingInitialData($initialData));
        $controller->setContainer($this->createContainerWithTwig());

        $controller->dataForm(Request::create('/ui/block/data-form?k=slider', 'POST', [
            'data' => ['id' => 'slider-f49f0e20', 'duration' => '5'],
        ]));

        $this->assertSame(['data' => ['duration' => '5']], $initialData);
    }
}
