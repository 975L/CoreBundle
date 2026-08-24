<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\ShortcutBuilder;
use c975L\ConfigBundle\Management\ShortcutProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ShortcutBuilderTest extends TestCase
{
    private function createProvider(array $shortcuts): ShortcutProviderInterface
    {
        $provider = $this->createStub(ShortcutProviderInterface::class);
        $provider->method('getShortcuts')->willReturn($shortcuts);

        return $provider;
    }

    // Translator double that returns the translation key untouched, so category order stays assertable
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        return $translator;
    }

    public function testGetCategoriesMergesEveryProvider(): void
    {
        $providerA = $this->createProvider([['label' => 'a']]);
        $providerB = $this->createProvider([['label' => 'b']]);
        $builder = new ShortcutBuilder([$providerA, $providerB], $this->createTranslator());

        $categories = $builder->getCategories();

        // Both land in the same fallback category, hence one row holding the two tiles
        $this->assertCount(1, $categories);
        $this->assertSame(['a', 'b'], array_column($categories[0]['shortcuts'], 'label'));
    }

    public function testGetCategoriesTranslatesTheCategoryLabelOnce(): void
    {
        $provider = $this->createProvider([['label' => 'a', 'category' => ShortcutProviderInterface::CATEGORY_EXPORT]]);
        $builder = new ShortcutBuilder([$provider], $this->createTranslator());

        $categories = $builder->getCategories();

        $this->assertSame('label.shortcuts_category_export', $categories[0]['label']);
        $this->assertSame('a', $categories[0]['shortcuts'][0]['label']);
    }

    public function testGetCategoriesGroupsByCategoryAndOrdersEachRowByLabel(): void
    {
        $provider = $this->createProvider([
            ['label' => 'z', 'category' => ShortcutProviderInterface::CATEGORY_SITE],
            ['label' => 'b', 'category' => ShortcutProviderInterface::CATEGORY_EXPORT],
            ['label' => 'a', 'category' => ShortcutProviderInterface::CATEGORY_EXPORT],
        ]);
        $builder = new ShortcutBuilder([$provider], $this->createTranslator());

        $categories = $builder->getCategories();

        $this->assertSame(['label.shortcuts_category_export', 'label.shortcuts_category_site'], array_column($categories, 'label'));
        $this->assertSame(['a', 'b'], array_column($categories[0]['shortcuts'], 'label'));
        $this->assertSame(['z'], array_column($categories[1]['shortcuts'], 'label'));
    }

    public function testGetCategoriesGroupsTilesOfTwoBundlesUnderTheSameCategory(): void
    {
        $providerOne = $this->createProvider([['label' => 'a', 'category' => ShortcutProviderInterface::CATEGORY_TOGGLE]]);
        $providerTwo = $this->createProvider([['label' => 'b', 'category' => ShortcutProviderInterface::CATEGORY_TOGGLE]]);
        $builder = new ShortcutBuilder([$providerOne, $providerTwo], $this->createTranslator());

        $categories = $builder->getCategories();

        $this->assertCount(1, $categories);
        $this->assertSame(['a', 'b'], array_column($categories[0]['shortcuts'], 'label'));
    }

    public function testGetCategoriesFallsBackToOtherCategoryWhenUnset(): void
    {
        $providerOther = $this->createProvider([['label' => 'no-category']]);
        $providerExport = $this->createProvider([['label' => 'export-one', 'category' => ShortcutProviderInterface::CATEGORY_EXPORT]]);
        $builder = new ShortcutBuilder([$providerOther, $providerExport], $this->createTranslator());

        // "Export" sorts before the fallback "label.shortcuts_category_other" key
        $this->assertSame(['label.shortcuts_category_export', 'label.shortcuts_category_other'], array_column($builder->getCategories(), 'label'));
    }

    // A provider saying nothing about 'warning' gets the historical behaviour: the tile of a thing currently on is the one painted (see ShortcutProviderInterface)
    public function testGetCategoriesFillsTheWarningFlagFromActive(): void
    {
        $provider = $this->createProvider([
            ['label' => 'on', 'active' => true],
            ['label' => 'off', 'active' => false],
            ['label' => 'no-active'],
        ]);
        $builder = new ShortcutBuilder([$provider], $this->createTranslator());

        $shortcuts = array_column($builder->getCategories()[0]['shortcuts'], 'warning', 'label');

        $this->assertTrue($shortcuts['on']);
        $this->assertFalse($shortcuts['off']);
        $this->assertFalse($shortcuts['no-active'], 'A tile with no "active" key at all must not be painted.');
    }

    // The whole point of the flag: an open registration is active and yet needs no warning
    public function testGetCategoriesKeepsTheWarningFlagSetByTheProvider(): void
    {
        $provider = $this->createProvider([
            ['label' => 'open', 'active' => true, 'warning' => false],
            ['label' => 'closed', 'active' => false, 'warning' => true],
        ]);
        $builder = new ShortcutBuilder([$provider], $this->createTranslator());

        $shortcuts = array_column($builder->getCategories()[0]['shortcuts'], 'warning', 'label');

        $this->assertFalse($shortcuts['open']);
        $this->assertTrue($shortcuts['closed']);
    }

    public function testGetCategoriesReturnsEmptyArrayWhenNoProviders(): void
    {
        $builder = new ShortcutBuilder([], $this->createTranslator());

        $this->assertSame([], $builder->getCategories());
    }
}
