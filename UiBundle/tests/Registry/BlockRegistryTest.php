<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Registry\BlockRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlockRegistryTest extends TestCase
{
    // Translator stub that echoes the key back, optionally with a locale-ish marker, so assertions can check both which key was looked up and which domain it was resolved in
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $key, array $params, ?string $domain = null) => $key . '[' . $domain . ']'
        );

        return $translator;
    }

    public function testGetThrowsForUnknownKind(): void
    {
        $registry = new BlockRegistry($this->createTranslator());

        $this->expectException(\InvalidArgumentException::class);
        $registry->get('unknown');
    }

    public function testHasReflectsRegisteredKinds(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');

        $this->assertTrue($registry->has('article'));
        $this->assertFalse($registry->has('missing'));
    }

    public function testGetLabelTranslatesUsingDeclaredDomain(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig', translationDomain: 'custom');

        $this->assertSame('label.article[custom]', $registry->getLabel('article'));
    }

    public function testGetDescriptionReturnsEmptyStringWhenNoneDeclared(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');

        $this->assertSame('', $registry->getDescription('article'));
    }

    public function testGetDescriptionTranslatesWhenDeclared(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register(
            'article',
            'label.article',
            ArticleFormStub::class,
            'article.html.twig',
            description: 'label.article_description'
        );

        $this->assertSame('label.article_description[ui]', $registry->getDescription('article'));
    }

    public function testGetFormClassGetTemplateAndGetMediaTypes(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register(
            'video',
            'label.video',
            ArticleFormStub::class,
            'video.html.twig',
            mediaTypes: ['video']
        );

        $this->assertSame(ArticleFormStub::class, $registry->getFormClass('video'));
        $this->assertSame('video.html.twig', $registry->getTemplate('video'));
        $this->assertSame(['video'], $registry->getMediaTypes('video'));
        $this->assertTrue($registry->hasMediaTypes('video'));
    }

    public function testGetMediaHelpDefaultsToGenericHelpWhenNoneDeclared(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');

        $this->assertSame('label.media_help', $registry->getMediaHelp('article'));
    }

    // "document_download" declares its own, distinct from the generic one - see BlockType/BlockFormController, which both delegate to this single source instead of duplicating the kind check
    public function testGetMediaHelpReturnsTheDeclaredKindSpecificValue(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register(
            'document_download',
            'label.document_download',
            ArticleFormStub::class,
            'document_download.html.twig',
            mediaHelp: 'label.document_download_media_help'
        );

        $this->assertSame('label.document_download_media_help', $registry->getMediaHelp('document_download'));
    }

    public function testHasMediaTypesIsFalseWhenNoneDeclared(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');

        $this->assertFalse($registry->hasMediaTypes('article'));
    }

    public function testIsMediaRequiredDefaultsToFalse(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');

        $this->assertFalse($registry->isMediaRequired('article'));
    }

    public function testIsMediaRequiredCanBeDeclaredTrue(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register(
            'banner_title',
            'label.banner_title',
            ArticleFormStub::class,
            'banner_title.html.twig',
            mediaTypes: ['image/*'],
            mediaRequired: true
        );

        $this->assertTrue($registry->isMediaRequired('banner_title'));
    }

    public function testAllowsMultiUploadDefaultsToFalse(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');

        $this->assertFalse($registry->allowsMultiUpload('article'));
    }

    public function testAllowsMultiUploadCanBeDeclaredTrue(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register(
            'slider',
            'label.slider',
            ArticleFormStub::class,
            'slider.html.twig',
            mediaTypes: ['image/*', 'video/*'],
            multiUpload: true
        );

        $this->assertTrue($registry->allowsMultiUpload('slider'));
    }

    public function testIsCacheableDefaultsToTrue(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');

        $this->assertTrue($registry->isCacheable('article'));
    }

    public function testIsCacheableCanBeDeclaredFalse(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('contact_form', 'label.contact_form', ArticleFormStub::class, 'contact.html.twig', cacheable: false);

        $this->assertFalse($registry->isCacheable('contact_form'));
    }

    public function testIsContainerDefaultsToFalse(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');

        $this->assertFalse($registry->isContainer('article'));
    }

    public function testIsContainerCanBeDeclaredTrue(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('flex_columns', 'label.flex_columns', ArticleFormStub::class, 'flex_columns.html.twig', container: true);

        $this->assertTrue($registry->isContainer('flex_columns'));
    }

    // A container kind must not be offered as a choice for its own slots, or an editor could nest containers indefinitely - see BlockType::addSlotsSubForm()
    public function testGroupedByCategoryExcludesContainerKindsFromSlotContext(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('flex_columns', 'label.flex_columns', ArticleFormStub::class, 'flex_columns.html.twig', container: true);
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');

        $forSlot = array_merge(...array_values($registry->groupedByCategory(BlockRegistry::SLOT_CONTEXT)));

        $this->assertArrayNotHasKey('label.flex_columns[ui]', $forSlot);
        $this->assertArrayHasKey('label.article[ui]', $forSlot);
    }

    // Outside the slot context, a container kind is a perfectly normal pickable block like any other
    public function testGroupedByCategoryIncludesContainerKindsInOrdinaryContexts(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('flex_columns', 'label.flex_columns', ArticleFormStub::class, 'flex_columns.html.twig', container: true);

        $grouped = array_merge(...array_values($registry->groupedByCategory()));

        $this->assertArrayHasKey('label.flex_columns[ui]', $grouped);
    }

    // With no "contexts" of its own it must stay pickable in a real named context, not only in none
    public function testGroupedByCategoryIncludesUnrestrictedContainerKindsInANamedOrdinaryContext(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('flex_columns', 'label.flex_columns', ArticleFormStub::class, 'flex_columns.html.twig', container: true);

        $grouped = array_merge(...array_values($registry->groupedByCategory('page')));

        $this->assertArrayHasKey('label.flex_columns[ui]', $grouped);
    }

    public function testGetSlotContextDefaultsToSlotContext(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('flex_columns', 'label.flex_columns', ArticleFormStub::class, 'flex_columns.html.twig', container: true);

        $this->assertSame(BlockRegistry::SLOT_CONTEXT, $registry->getSlotContext('flex_columns'));
    }

    public function testGetSlotContextCanBeOverridden(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register(
            'flex_column',
            'label.flex_column',
            ArticleFormStub::class,
            'flex_column.html.twig',
            container: true,
            slotContext: BlockRegistry::NESTED_SLOT_CONTEXT
        );

        $this->assertSame(BlockRegistry::NESTED_SLOT_CONTEXT, $registry->getSlotContext('flex_column'));
    }

    // A container may opt into one slot context only, so a column still can't hold another column
    public function testGroupedByCategoryLetsAContainerOptIntoOneSpecificSlotContext(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('flex_columns', 'label.flex_columns', ArticleFormStub::class, 'flex_columns.html.twig', container: true);
        $registry->register(
            'flex_column',
            'label.flex_column',
            ArticleFormStub::class,
            'flex_column.html.twig',
            container: true,
            contexts: [BlockRegistry::SLOT_CONTEXT],
            slotContext: BlockRegistry::NESTED_SLOT_CONTEXT
        );

        $forRowSlot = array_merge(...array_values($registry->groupedByCategory(BlockRegistry::SLOT_CONTEXT)));
        $forColumnSlot = array_merge(...array_values($registry->groupedByCategory(BlockRegistry::NESTED_SLOT_CONTEXT)));

        $this->assertArrayHasKey('label.flex_column[ui]', $forRowSlot);
        $this->assertArrayNotHasKey('label.flex_columns[ui]', $forRowSlot);
        $this->assertArrayNotHasKey('label.flex_column[ui]', $forColumnSlot);
    }

    // isAllowedInContext() backs BlockMoveController, exercised directly rather than through the picker
    public function testIsAllowedInContextAllowsAnOrdinaryKindInAnySlotContext(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('card', 'label.card', ArticleFormStub::class, 'card.html.twig');

        $this->assertTrue($registry->isAllowedInContext('card', BlockRegistry::SLOT_CONTEXT));
    }

    public function testIsAllowedInContextRejectsAContainerNotOptedIntoThatSlotContext(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('section_cards', 'label.section_cards', ArticleFormStub::class, 'section_cards.html.twig', container: true);

        $this->assertFalse($registry->isAllowedInContext('section_cards', BlockRegistry::SLOT_CONTEXT));
    }

    public function testIsAllowedInContextAllowsAContainerThatOptedIntoThatSpecificSlotContext(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register(
            'flex_column',
            'label.flex_column',
            ArticleFormStub::class,
            'flex_column.html.twig',
            container: true,
            contexts: [BlockRegistry::SLOT_CONTEXT]
        );

        $this->assertTrue($registry->isAllowedInContext('flex_column', BlockRegistry::SLOT_CONTEXT));
    }

    // A slot context declared by another bundle (SiteBundle's "menu_slot") is a slot context all the same: the depth guard reads them off the registered containers rather than off a list this registry holds
    public function testIsAllowedInContextGuardsTheDepthOfASatelliteBundlesOwnSlotContext(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register(
            'menu_group',
            'label.menu_group',
            ArticleFormStub::class,
            'menu_group.html.twig',
            container: true,
            contexts: ['menu'],
            slotContext: 'menu_slot'
        );
        $registry->register('card', 'label.card', ArticleFormStub::class, 'card.html.twig');

        $this->assertTrue($registry->isAllowedInContext('card', 'menu_slot'));
        $this->assertFalse($registry->isAllowedInContext('menu_group', 'menu_slot'));
    }

    // A menu's own kinds only ever opt into the slot context of the container meant for them, so a generic container picked there would be a group nothing can be put into - offered by the picker, then refusing every drop
    public function testIsAllowedInContextRejectsAGenericContainerInAMenu(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('block_group', 'label.block_group', ArticleFormStub::class, 'block_group.html.twig', container: true);

        $this->assertFalse($registry->isAllowedInContext('block_group', BlockRegistry::MENU_CONTEXT));
    }

    // The container declared for menus keeps its place there, and so does every non-container kind: a footer takes any of them on purpose
    public function testIsAllowedInContextAllowsTheMenusOwnContainerAndOrdinaryKinds(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register(
            'menu_group',
            'label.menu_group',
            ArticleFormStub::class,
            'menu_group.html.twig',
            container: true,
            contexts: [BlockRegistry::MENU_CONTEXT],
            slotContext: 'menu_slot'
        );
        $registry->register('card', 'label.card', ArticleFormStub::class, 'card.html.twig');

        $this->assertTrue($registry->isAllowedInContext('menu_group', BlockRegistry::MENU_CONTEXT));
        $this->assertTrue($registry->isAllowedInContext('card', BlockRegistry::MENU_CONTEXT));
    }

    public function testIsAllowedInContextRejectsANonPickableKindEvenOutsideAnySlotContext(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('social_links', 'label.social_links', ArticleFormStub::class, 'social_links.html.twig', pickable: false);

        $this->assertFalse($registry->isAllowedInContext('social_links', null));
    }

    public function testGetBundleReturnsRegisteredValue(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('legal_model', 'label.legal_model', ArticleFormStub::class, '@c975LSite/blocks/LegalModel.html.twig', bundle: 'Site');

        $this->assertSame('Site', $registry->getBundle('legal_model'));
    }

    public function testGetBundleDefaultsToEmptyString(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');

        $this->assertSame('', $registry->getBundle('article'));
    }

    // Same grouping/ordering rules as groupedByCategory(), but keyed by bundle instead
    public function testGroupedByBundleGroupsAndOrdersEntries(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('hero', 'label.hero', ArticleFormStub::class, 'hero.html.twig', bundle: 'Ui', priority: 10);
        $registry->register('alert', 'label.alert', ArticleFormStub::class, 'alert.html.twig', bundle: 'Ui');
        $registry->register('legal_model', 'label.legal_model', ArticleFormStub::class, 'legal.html.twig', bundle: 'Site');

        $grouped = $registry->groupedByBundle();

        $this->assertSame(['Site', 'Ui'], array_keys($grouped));
        $this->assertSame(['hero', 'alert'], array_values($grouped['Ui']));
    }

    public function testGroupedByBundleExcludesNonPickableKinds(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig', bundle: 'Ui');
        $registry->register('social_links', 'label.social_links', ArticleFormStub::class, 'social.html.twig', bundle: 'Social', pickable: false);

        $grouped = $registry->groupedByBundle();

        $this->assertArrayHasKey('Ui', $grouped);
        $this->assertArrayNotHasKey('Social', $grouped);
    }

    // groupedByBundle() is memoized independently from groupedByCategory()
    public function testGroupedByBundleResultIsCached(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig', bundle: 'Ui');
        $first = $registry->groupedByBundle();

        $registry->register('video', 'label.video', ArticleFormStub::class, 'video.html.twig', bundle: 'Ui');
        $second = $registry->groupedByBundle();

        $this->assertSame($first, $second);
    }

    // Same contexts filtering rules as groupedByCategory(): a kind restricted to a context only appears when that context is asked for
    public function testGroupedByBundleExcludesKindsRestrictedToOtherContexts(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('menu_link', 'label.menu_link', ArticleFormStub::class, 'menu_link.html.twig', bundle: 'Ui', contexts: ['menu']);

        $forPage = $registry->groupedByBundle('page');
        $forMenu = $registry->groupedByBundle('menu');

        $this->assertArrayNotHasKey('Ui', $forPage);
        $this->assertArrayHasKey('Ui', $forMenu);
    }

    // Each distinct $context is cached separately, same as groupedByCategory()
    public function testGroupedByBundleCachesPerContext(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('menu_link', 'label.menu_link', ArticleFormStub::class, 'menu_link.html.twig', bundle: 'Ui', contexts: ['menu']);

        $registry->groupedByBundle('menu');
        $forPage = $registry->groupedByBundle('page');

        $this->assertArrayNotHasKey('Ui', $forPage);
    }

    public function testAllReturnsEveryRegisteredBlockConfig(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');
        $registry->register('video', 'label.video', ArticleFormStub::class, 'video.html.twig');

        $this->assertSame(['article', 'video'], array_keys($registry->all()));
    }

    // Non-pickable kinds (singleton blocks with their own dedicated admin entry) must never appear
    public function testGroupedByCategoryExcludesNonPickableKinds(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');
        $registry->register('social_links', 'label.social_links', ArticleFormStub::class, 'social.html.twig', pickable: false);

        $grouped = $registry->groupedByCategory();

        $categories = array_merge(...array_values($grouped));
        $this->assertArrayHasKey('label.article[ui]', $categories);
        $this->assertArrayNotHasKey('label.social_links[ui]', $categories);
    }

    // Within a category, entries are ordered by priority (highest first), then alphabetically by label
    public function testGroupedByCategoryOrdersByPriorityThenLabel(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('zebra', 'label.zebra', ArticleFormStub::class, 'zebra.html.twig', category: 'cat', priority: 0);
        $registry->register('apple', 'label.apple', ArticleFormStub::class, 'apple.html.twig', category: 'cat', priority: 0);
        $registry->register('important', 'label.important', ArticleFormStub::class, 'important.html.twig', category: 'cat', priority: 10);

        $grouped = $registry->groupedByCategory();
        $kinds = array_values($grouped['cat[ui]']);

        $this->assertSame(['important', 'apple', 'zebra'], $kinds);
    }

    // Categories themselves are sorted alphabetically (case-insensitive)
    public function testGroupedByCategorySortsCategoriesAlphabetically(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('a', 'label.a', ArticleFormStub::class, 'a.html.twig', category: 'zeta');
        $registry->register('b', 'label.b', ArticleFormStub::class, 'b.html.twig', category: 'alpha');

        $grouped = $registry->groupedByCategory();

        $this->assertSame(['alpha[ui]', 'zeta[ui]'], array_keys($grouped));
    }

    // The choice label appends the translated description in parentheses when one was declared
    public function testGroupedByCategoryAppendsDescriptionToChoiceLabel(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register(
            'article',
            'label.article',
            ArticleFormStub::class,
            'article.html.twig',
            description: 'label.article_description'
        );

        $grouped = $registry->groupedByCategory();
        $label = array_key_first($grouped['label.category_general[ui]']);

        $this->assertSame('label.article[ui] (label.article_description[ui])', $label);
    }

    // groupedByCategory() is memoized: registering a new kind after the first call must not appear
    public function testGroupedByCategoryResultIsCached(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');
        $first = $registry->groupedByCategory();

        $registry->register('video', 'label.video', ArticleFormStub::class, 'video.html.twig');
        $second = $registry->groupedByCategory();

        $this->assertSame($first, $second);
    }

    // A kind declared with no "contexts" at all (the default) is available regardless of which context is asked for - e.g. legal_model, usable both on a Page and (in theory) a Menu
    public function testGroupedByCategoryIncludesUncontextualizedKindsInAnyContext(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');

        $forPage = array_merge(...array_values($registry->groupedByCategory('page')));
        $forMenu = array_merge(...array_values($registry->groupedByCategory('menu')));

        $this->assertArrayHasKey('label.article[ui]', $forPage);
        $this->assertArrayHasKey('label.article[ui]', $forMenu);
    }

    // A kind restricted to one or more contexts (e.g. SiteBundle's "menu_link", contexts: ['menu']) only appears when that matching context is asked for, not in unrelated ones
    public function testGroupedByCategoryExcludesKindsRestrictedToOtherContexts(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('menu_link', 'label.menu_link', ArticleFormStub::class, 'menu_link.html.twig', contexts: ['menu']);

        $forPage = array_merge(...array_values($registry->groupedByCategory('page')));
        $forMenu = array_merge(...array_values($registry->groupedByCategory('menu')));

        $this->assertArrayNotHasKey('label.menu_link[ui]', $forPage);
        $this->assertArrayHasKey('label.menu_link[ui]', $forMenu);
    }

    // An exclusive context (MENU_NAVBAR_CONTEXT) reverses the default rule: a kind declaring no "contexts" at all, available everywhere else, is kept out of it - a navbar only ever offers the kinds that opted in ("menu_link")
    public function testExclusiveContextOnlyOffersKindsThatOptedIn(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('article', 'label.article', ArticleFormStub::class, 'article.html.twig');
        $registry->register('menu_link', 'label.menu_link', ArticleFormStub::class, 'menu_link.html.twig', contexts: [BlockRegistry::MENU_CONTEXT, BlockRegistry::MENU_NAVBAR_CONTEXT]);

        $forNavbar = array_merge(...array_values($registry->groupedByCategory(BlockRegistry::MENU_NAVBAR_CONTEXT)));
        $forMenu = array_merge(...array_values($registry->groupedByCategory(BlockRegistry::MENU_CONTEXT)));

        $this->assertSame(['label.menu_link[ui]' => 'menu_link'], $forNavbar);
        $this->assertArrayHasKey('label.article[ui]', $forMenu);
        $this->assertTrue($registry->isAllowedInContext('menu_link', BlockRegistry::MENU_NAVBAR_CONTEXT));
        $this->assertFalse($registry->isAllowedInContext('article', BlockRegistry::MENU_NAVBAR_CONTEXT));
    }

    // Calling groupedByCategory() with no context at all (the pre-existing call signature) skips the contexts filter entirely, so callers that haven't started passing a context yet see everything
    public function testGroupedByCategoryWithoutContextIgnoresContextsRestriction(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('menu_link', 'label.menu_link', ArticleFormStub::class, 'menu_link.html.twig', contexts: ['menu']);

        $grouped = array_merge(...array_values($registry->groupedByCategory()));

        $this->assertArrayHasKey('label.menu_link[ui]', $grouped);
    }

    // Each distinct $context is cached separately - a lookup for "menu" must not leak into "page"'s cache
    public function testGroupedByCategoryCachesPerContext(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('menu_link', 'label.menu_link', ArticleFormStub::class, 'menu_link.html.twig', contexts: ['menu']);

        $registry->groupedByCategory('menu');
        $forPage = array_merge(...array_values($registry->groupedByCategory('page')));

        $this->assertArrayNotHasKey('label.menu_link[ui]', $forPage);
    }

    // Optgroups follow CATEGORY_ORDER, not alphabetical order
    public function testGroupedByCategoryOrdersCategoriesByCategoryOrderInsteadOfAlphabetically(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('slider', 'label.slider', ArticleFormStub::class, 'slider.html.twig', category: 'label.category_media');
        $registry->register('hero', 'label.hero', ArticleFormStub::class, 'hero.html.twig', category: 'label.category_sections');

        $grouped = $registry->groupedByCategory();

        $this->assertSame(['label.category_sections[ui]', 'label.category_media[ui]'], array_keys($grouped));
    }

    // A category absent from CATEGORY_ORDER (e.g. a future bundle's own) falls back after every listed one
    public function testGroupedByCategoryPlacesUnlistedCategoriesAfterKnownOnes(): void
    {
        $registry = new BlockRegistry($this->createTranslator());
        $registry->register('custom', 'label.custom', ArticleFormStub::class, 'custom.html.twig', category: 'label.category_custom');
        $registry->register('legal_model', 'label.legal_model', ArticleFormStub::class, 'legal.html.twig', category: 'label.category_legal');

        $grouped = $registry->groupedByCategory();

        $this->assertSame(['label.category_legal[ui]', 'label.category_custom[ui]'], array_keys($grouped));
    }
}
