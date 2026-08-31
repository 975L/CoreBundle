<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use Symfony\Contracts\Translation\TranslatorInterface;

// Check readme for use
class BlockRegistry
{
    // Default context of a container's own "slots"; containers are excluded from it, there being no depth guard
    public const SLOT_CONTEXT = 'flex_slot';

    // The slots of a nested container, kept separate so a kind can opt into one nesting depth and not the other
    public const NESTED_SLOT_CONTEXT = 'flex_slot_nested';

    // Its own context because it is exclusive: only a column carries the width option a bare slot can't store
    public const FLEX_COLUMNS_SLOT_CONTEXT = 'flex_columns_slot';

    // A navbar must stay a plain list of links, hence its exclusive context; a footer/email menu takes any kind
    public const MENU_CONTEXT = 'menu';
    public const MENU_NAVBAR_CONTEXT = 'menu_navbar';

    // Contexts offering only kinds that declared them, the opposite of the default "no contexts = everywhere"
    private const array EXCLUSIVE_CONTEXTS = [self::MENU_NAVBAR_CONTEXT, self::FLEX_COLUMNS_SLOT_CONTEXT];

    // Optgroup order, untranslated so it holds across locales; an unlisted category falls after, alphabetically
    private const array CATEGORY_ORDER = [
        'label.category_sections',
        'label.category_elements',
        'label.category_text',
        'label.category_media',
        'label.category_forms',
        'label.category_navigation',
        'label.category_seo',
        'label.category_legal',
        'label.category_twig',
    ];

    private array $blocks = [];
    private array $labelCache = [];
    private array $descriptionCache = [];
    private array $categoryCache = [];
    private array $groupedCache = [];
    private array $groupedByBundleCache = [];

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    // 19 scalars, past the sixteen phpmd.xml.dist calls the limit: what a "ui.block" tag declares, owed a value object of its own
    /** @SuppressWarnings(PHPMD.ExcessiveParameterList) */
    public function register(
        string $kind,
        string $label,
        string $formClass,
        string $template,
        string $category = 'label.category_general',
        array $mediaTypes = [],
        string $translationDomain = 'ui',
        string $description = '',
        bool $pickable = true,
        int $priority = 0,
        bool $cacheable = true,
        array $contexts = [],
        bool $mediaRequired = false,
        bool $multiUpload = false,
        string $bundle = '',
        bool $container = false,
        string $slotContext = self::SLOT_CONTEXT,
        string $mediaHelp = '',
        array $translatable = [],
    ): void {
        $this->blocks[$kind] = [
            'label' => $label,
            'domain' => $translationDomain,
            'form' => $formClass,
            'template' => $template,
            'category' => $category,
            'mediaTypes' => $mediaTypes,
            'description' => $description,
            'pickable' => $pickable,
            'priority' => $priority,
            'cacheable' => $cacheable,
            'contexts' => $contexts,
            'mediaRequired' => $mediaRequired,
            'multiUpload' => $multiUpload,
            'bundle' => $bundle,
            'container' => $container,
            'slotContext' => $slotContext,
            'mediaHelp' => $mediaHelp,
            'translatable' => $translatable,
        ];
    }

    /**
     * The keys of this kind's own data a translation may cover, declared one by one in its "ui.block" tag.
     *
     * Nothing declared means nothing translatable, which is what every kind means until it says otherwise: there is
     * no discovery from the form type, a text field holding a css class or an icon name having no business being
     * offered for translation.
     *
     * @return list<string>
     */
    public function getTranslatable(string $kind): array
    {
        return $this->has($kind) ? $this->get($kind)['translatable'] ?? [] : [];
    }

    // Gets the translated label of a block kind (falls back to the raw label if untranslated)
    public function getLabel(string $kind): string
    {
        if (!isset($this->labelCache[$kind])) {
            $block = $this->get($kind);
            $this->labelCache[$kind] = $this->translator->trans($block['label'], [], $block['domain']);
        }

        return $this->labelCache[$kind];
    }

    // Gets the translated description of a block kind, empty if none was declared
    public function getDescription(string $kind): string
    {
        if (!isset($this->descriptionCache[$kind])) {
            $block = $this->get($kind);
            $this->descriptionCache[$kind] = '' === $block['description']
                ? ''
                : $this->translator->trans($block['description'], [], $block['domain']);
        }

        return $this->descriptionCache[$kind];
    }

    // Gets the translated category of a block kind, using the same translation domain as its label
    public function getCategory(string $kind): string
    {
        if (!isset($this->categoryCache[$kind])) {
            $block = $this->get($kind);
            $this->categoryCache[$kind] = $this->translator->trans($block['category'], [], $block['domain']);
        }

        return $this->categoryCache[$kind];
    }

    // Gets the bundle a block kind was registered from (derived from its template's Twig namespace by BlockRegistryPass, e.g. "Ui", "Site", "Social" - empty when that derivation failed)
    public function getBundle(string $kind): string
    {
        return $this->get($kind)['bundle'];
    }

    public function get(string $kind): array
    {
        if (!isset($this->blocks[$kind])) {
            throw new \InvalidArgumentException("Unknown block: {$kind}");
        }

        return $this->blocks[$kind];
    }

    public function has(string $type): bool
    {
        return isset($this->blocks[$type]);
    }

    public function all(): array
    {
        return $this->blocks;
    }

    public function getFormClass(string $kind): string
    {
        return $this->get($kind)['form'];
    }

    public function getTemplate(string $kind): string
    {
        return $this->get($kind)['template'];
    }

    public function getMediaTypes(string $kind): array
    {
        return $this->get($kind)['mediaTypes'];
    }

    public function hasMediaTypes(string $kind): bool
    {
        return !empty($this->get($kind)['mediaTypes']);
    }

    // The "medias" field's help text - a kind-specific one when declared (e.g. "document_download"'s one-card-per-file behaviour), the generic one otherwise. Single source shared by BlockType and BlockFormController's AJAX-loaded preview, instead of each duplicating the same kind check.
    public function getMediaHelp(string $kind): string
    {
        $help = $this->get($kind)['mediaHelp'];

        return '' !== $help ? $help : 'label.media_help';
    }

    // True for kinds that can't be saved without at least one attached media (e.g. "banner_title", whose background image isn't optional decoration but the whole point of the block) - enforced by RequiredMediaValidator on the Block entity itself
    public function isMediaRequired(string $kind): bool
    {
        return $this->get($kind)['mediaRequired'];
    }

    // True for kinds whose media collection additionally exposes a "select several files at once" input (e.g. "slider", "article") instead of the default one-file-per-row Add button
    public function allowsMultiUpload(string $kind): bool
    {
        return $this->get($kind)['multiUpload'];
    }

    // False for kinds whose rendered output isn't safe to reuse across requests (e.g. embeds a Symfony form with its own CSRF token, like "contact_form")
    public function isCacheable(string $kind): bool
    {
        return $this->get($kind)['cacheable'];
    }

    // True for kinds embedding their own nested Block rows as "slots"
    public function isContainer(string $kind): bool
    {
        return $this->get($kind)['container'];
    }

    // The context a container's "slots" collection is built with, only meaningful when isContainer() is true
    public function getSlotContext(string $kind): string
    {
        return $this->get($kind)['slotContext'];
    }

    // The contexts a kind opted into, empty meaning "offered everywhere" - a non-empty list is what makes a kind reachable only inside a parent (a container's slots, a menu), which is what a listing of every kind has to say of one it can show nothing of on its own
    /**
     * @return list<string>
     */
    public function getContexts(string $kind): array
    {
        return $this->get($kind)['contexts'];
    }

    // groupBy()'s own eligibility rules, exposed standalone so BlockMoveController can check an existing block
    public function isAllowedInContext(string $kind, ?string $context): bool
    {
        $config = $this->get($kind);

        if (!$config['pickable']) {
            return false;
        }
        if (null !== $context && !empty($config['contexts']) && !in_array($context, $config['contexts'], true)) {
            return false;
        }
        if (in_array($context, self::EXCLUSIVE_CONTEXTS, true) && !in_array($context, $config['contexts'], true)) {
            return false;
        }

        // Any context a registered container builds its slots with, not just this bundle's own: a satellite bundle declaring a slot_context of its own (SiteBundle's "menu_slot") gets the same depth guard, without this registry ever having to know that context's name
        $isSlotContext = in_array($context, $this->slotContexts(), true);

        // A menu joins them: its own kinds ("menu_link") only ever opt into the slot context of the container meant for them (SiteBundle's "menu_group"), so a generic container picked in a menu would be a group no link can be put into - the picker offers it, then refuses every drop, which is exactly what the editor cannot make sense of. Containers only, a menu otherwise taking any kind on purpose
        $isContainerOnlyOnOptIn = $isSlotContext || self::MENU_CONTEXT === $context;

        return !($isContainerOnlyOnOptIn && $config['container'] && !in_array($context, $config['contexts'], true));
    }

    /**
     * Every context a registered container builds its slots with, i.e. the union of the "slot_context" tag
     * attributes - the contexts where a container is only offered if it opted into that exact one.
     *
     * @return list<string>
     */
    private function slotContexts(): array
    {
        return array_values(array_unique(array_column(
            array_filter($this->blocks, static fn (array $config): bool => $config['container']),
            'slotContext'
        )));
    }

    // Result only depends on the static block registrations, cached per $context after its first call - excludes non-pickable kinds (singleton blocks with their own dedicated admin entry, e.g. SocialBundle's "social_links": offering them here would let editors create duplicate, independently-filled instances instead of reusing the single site-wide one found via BlockRepository::findOneByKind()), and kinds restricted to other contexts (e.g. SiteBundle's "menu_link", declared with contexts: ['menu'] so it doesn't leak into a Page's block picker). A kind declared with no contexts at all is available everywhere, and passing no $context here skips the contexts filter entirely - both keep existing callers (that don't pass $context yet) working unchanged.
    public function groupedByCategory(?string $context = null): array
    {
        return $this->groupBy(
            fn (string $kind) => $this->getCategory($kind),
            $context,
            $this->groupedCache,
            fn (string $kind, array $config) => $config['category']
        );
    }

    // Same grouping/filtering as groupedByCategory(), but by originating bundle instead of functional category - used to build a showcase page per bundle (e.g. 975l.com's public block demo) instead of the kind-picker's functional grouping. Kinds with no derivable bundle group under ''.
    public function groupedByBundle(?string $context = null): array
    {
        return $this->groupBy(fn (string $kind, array $config) => $config['bundle'], $context, $this->groupedByBundleCache);
    }

    // Groups eligible kinds by $keyFn, each group ordered by priority; $orderKeyFn ranks the optgroups themselves
    private function groupBy(callable $keyFn, ?string $context, array &$cache, ?callable $orderKeyFn = null): array
    {
        $cacheKey = $context ?? '';
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $grouped = [];
        $orderKeys = [];
        foreach ($this->blocks as $kind => $config) {
            if (!$this->isAllowedInContext($kind, $context)) {
                continue;
            }
            $groupKey = $keyFn($kind, $config);
            $grouped[$groupKey][] = [
                'kind' => $kind,
                'label' => $this->getChoiceLabel($kind),
                'priority' => $config['priority'],
            ];
            if (null !== $orderKeyFn && !isset($orderKeys[$groupKey])) {
                $orderKeys[$groupKey] = $orderKeyFn($kind, $config);
            }
        }

        $this->sortGroups($grouped, $orderKeys, null !== $orderKeyFn);

        // Highest priority first; alphabetical as tie-breaker so unranked (priority 0) blocks stay predictable
        foreach ($grouped as $key => $entries) {
            usort($entries, fn (array $a, array $b) => $b['priority'] <=> $a['priority'] ?: strcasecmp($a['label'], $b['label']));
            $grouped[$key] = array_column($entries, 'kind', 'label');
        }

        return $cache[$cacheKey] = $grouped;
    }

    // Ranks each optgroup by its position in CATEGORY_ORDER, alphabetical tie-break for anything not listed there - plain alphabetical when the caller declares no order of its own
    private function sortGroups(array &$grouped, array $orderKeys, bool $ranked): void
    {
        if (!$ranked) {
            ksort($grouped, SORT_FLAG_CASE | SORT_STRING);

            return;
        }

        uksort($grouped, fn (string $a, string $b): int => $this->categoryRank($orderKeys[$a]) <=> $this->categoryRank($orderKeys[$b]) ?: strcasecmp($a, $b));
    }

    // Where a category stands in the declared order, anything unlisted ranking last
    private function categoryRank(string $category): int
    {
        $position = array_search($category, self::CATEGORY_ORDER, true);

        return false === $position ? count(self::CATEGORY_ORDER) : $position;
    }

    // Builds the "kind" choice label: name, plus a short description in parentheses when declared. Kept as plain text (no markup) so the "kind" field's <optgroup> categories stay intact - EasyAdmin's ea-autocomplete widget only preserves grouping on a plain native <select>.
    private function getChoiceLabel(string $kind): string
    {
        $label = $this->getLabel($kind);
        $description = $this->getDescription($kind);

        if ('' === $description) {
            return $label;
        }

        return sprintf('%s (%s)', $label, $description);
    }
}
