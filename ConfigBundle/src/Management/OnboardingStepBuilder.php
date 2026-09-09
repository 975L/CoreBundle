<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Builds the guided-tour steps from MenuBuilder's already-aggregated menus/links, covering every entry so the tour reflects the whole sidebar - a menu or a link needing a role the current user lacks (see MenuProviderInterface) is skipped though, since its sidebar target isn't even rendered for them. 'description' (see MenuProviderInterface) stays optional - a step for an item without one just shows its label, no explanatory text. Each step carries the item's own resolved URL rather than an invented id/slug: assets/js/onboarding-tour.js matches it against the sidebar's own `a[href]` (no EasyAdmin template override needed, see Sidebar/Item.html.twig), the same deterministic url-generation approach already used by ConfigEditUrlResolver
class OnboardingStepBuilder
{
    public function __construct(
        private readonly MenuBuilder $menuBuilder,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // [{url, label, description}], one per menu/link (both kinds needing a role the user lacks excluded) - every entry the sidebar draws in a section, menu or internal link alike, follows MenuBuilder::getOrderedMenus(), the same essential-then-advanced-across-sections order the sidebar itself renders, with the links leaving the admin (already alphabetical/pinned-last, matching their own "Liens" section) appended after, that section being drawn last
    public function getSteps(): array
    {
        $steps = [];

        foreach ($this->menuBuilder->getOrderedMenus() as $entry) {
            if (!$this->isGranted($entry)) {
                continue;
            }

            // Same split as MenuBuilder::getMenuItems() makes on the very same entries (an internal link carries a route, not a controller), and for the same reason: a step is highlighted by matching its url against the sidebar's own href, so both kinds have to be spelled the way the sidebar spells them
            $url = isset($entry['controller'])
                ? $this->adminUrlGenerator->unsetAll()
                    ->setController($entry['controller'])
                    // Same action resolution as MenuBuilder::getMenuItems(): an item naming its action has to be read the same way here
                    ->setAction($entry['action'] ?? Action::INDEX)
                    ->generateUrl()
                : $this->linkUrl($entry);

            $steps[] = $this->buildStep($url, $entry);
        }

        // What is left is every link leaving the admin, the ones getOrderedMenus() skips because the sidebar gathers them in its own section, below every menu
        foreach ($this->menuBuilder->getLinks() as $link) {
            if (!MenuBuilder::leavesTheAdmin($link) || !$this->isGranted($link)) {
                continue;
            }

            $steps[] = $this->buildStep($this->linkUrl($link), $link);
        }

        return $steps;
    }

    // Same defaults as MenuBuilder gives the sidebar item - a menu falls back on the admin role, a link is gated only when it names one (see buildMenuItem()/buildLinkItem()) - and read here for the same reason it is read there: the tour is highlighted by matching an href, so an entry the sidebar doesn't draw has nothing to point at
    private function isGranted(array $entry): bool
    {
        if (isset($entry['controller'])) {
            return $this->security->isGranted($entry['role'] ?? $this->configService->get('site-role-admin'));
        }

        return !isset($entry['role']) || $this->security->isGranted($entry['role']);
    }

    // Same url resolution as MenuBuilder::linkUrl(): a literal url wins over a route, resolved absolute only for a link leaving the admin
    private function linkUrl(array $link): string
    {
        $referenceType = isset($link['target']) ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH;

        return $link['url'] ?? $this->urlGenerator->generate($link['name'], [], $referenceType);
    }

    // Same label/description resolution as MenuBuilder::getMenuItems() for a link (see its 'label_parameters' handling) - kept in sync by hand since both operate on the same MenuProviderInterface item shape
    private function buildStep(string $url, array $item): array
    {
        $label = $this->translator->trans($item['label'], $item['label_parameters'] ?? [], $item['translation_domain']);
        $description = empty($item['description']) ? '' : $this->translator->trans($item['description'], [], $item['translation_domain']);

        return [
            'url' => $url,
            'label' => $label,
            'description' => $description,
            // What the step sounds like spoken, read by the films of the back office and drawn nowhere. An entry nobody has written a sentence for falls back to its caption, better said badly than not said at all
            'narration' => empty($item['narration'])
                ? trim(rtrim($label, " \t.") . '. ' . $description)
                : $this->translator->trans($item['narration'], [], $item['translation_domain'] . GuidedProjectBuilder::NARRATION_DOMAIN_SUFFIX),
        ];
    }
}
