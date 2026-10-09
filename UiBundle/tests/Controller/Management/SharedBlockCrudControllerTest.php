<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Management\ContentLocaleScreen;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\UiBundle\Controller\Management\SharedBlockCrudController;
use c975L\UiBundle\Registry\BlockLocationRegistry;
use c975L\UiBundle\Repository\SharedBlockRepository;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\SharedBlockUsage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The edit screen opened on another language offers the blocks alone, written in that language, with neither add nor delete
class SharedBlockCrudControllerTest extends TestCase
{
    // The language travels down into every block's form, and the run cannot be recomposed there
    public function testALanguageScreenOffersTheBlocksAloneInThatLanguage(): void
    {
        $fields = iterator_to_array($this->createController('es')->configureFields(Crud::PAGE_EDIT), false);

        $this->assertCount(1, $fields);
        $options = $fields[0]->getAsDto()->getFormTypeOptions();
        $this->assertSame('blocks', $fields[0]->getAsDto()->getProperty());
        $this->assertSame('es', $options['entry_options']['translation_locale']);
        $this->assertArrayNotHasKey('allow_add', $options);
        $this->assertArrayNotHasKey('allow_delete', $options);
    }

    // A language the site does not declare opens the ordinary screen, name first
    public function testAnUndeclaredLanguageLeavesTheOrdinaryScreen(): void
    {
        $fields = iterator_to_array($this->createController('de')->configureFields(Crud::PAGE_EDIT), false);

        $this->assertSame('name', $fields[0]->getAsDto()->getProperty());
    }

    // Only the edit screen has a language of its own: the creation form ignores the parameter
    public function testTheCreationFormIgnoresTheLanguage(): void
    {
        $fields = iterator_to_array($this->createController('es')->configureFields(Crud::PAGE_NEW), false);

        $this->assertSame('name', $fields[0]->getAsDto()->getProperty());
    }

    private function createController(string $asked): SharedBlockCrudController
    {
        $requestStack = new RequestStack([new Request([ContentLocaleScreen::PARAM => $asked])]);
        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('getTranslatableLocales')->willReturn(['es']);

        return new SharedBlockCrudController(
            $this->createStub(ConfigServiceInterface::class),
            $this->createStub(TranslatorInterface::class),
            new AdminContextProvider(new RequestStack()),
            $this->createStub(AdminUrlGeneratorInterface::class),
            $this->createStub(BlockMoveRowAttrBuilder::class),
            $this->createStub(SharedBlockRepository::class),
            $this->createStub(SharedBlockUsage::class),
            new BlockLocationRegistry(),
            $this->createStub(SluggerInterface::class),
            new ContentLocaleScreen($requestStack, $this->createStub(AdminUrlGeneratorInterface::class), new SiteLocales(['fr', 'es'], 'fr')),
            $contentTranslator,
        );
    }
}
