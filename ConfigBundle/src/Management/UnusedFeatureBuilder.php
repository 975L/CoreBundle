<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// What the installed bundles offer and the site doesn't use yet: the sidebar's CRUDs with nothing in them, read off the menus rather than declared bundle by bundle so one a bundle ships tomorrow is found the same way, then the config entries switching a feature on still left empty (see UnusedConfigFeatureReader)
class UnusedFeatureBuilder
{
    // Enough to be read at a glance, a few others coming round the next day
    public const int SHOWN = 3;

    public function __construct(
        private readonly MenuBuilder $menuBuilder,
        private readonly MenuEntryResolver $menuEntryResolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly UnusedConfigFeatureReader $unusedConfigFeatureReader,
        private readonly TranslatorInterface $translator,
        private readonly ClockInterface $clock,
    ) {
    }

    // [{label, description, url}], the unused features the current user could open, a few at a time - rotated by the day rather than drawn at random, so the list holds still between two reloads
    public function getFeatures(): array
    {
        $features = [];
        foreach ($this->menuBuilder->getOrderedMenus() as $entry) {
            if ($this->menuEntryResolver->isGranted($entry) && $this->isUnused($entry)) {
                $features[] = [
                    'label' => $this->translator->trans($entry['label'], [], $entry['translation_domain']),
                    'description' => empty($entry['description']) ? '' : $this->translator->trans($entry['description'], [], $entry['translation_domain']),
                    'url' => $this->menuEntryResolver->url($entry),
                ];
            }
        }

        // The screens first, then the keys left empty
        $features = [...$features, ...$this->unusedConfigFeatureReader->getFeatures()];

        if (\count($features) <= self::SHOWN) {
            return $features;
        }

        $offset = (int) $this->clock->now()->format('z') % \count($features);

        return \array_slice([...\array_slice($features, $offset), ...\array_slice($features, 0, $offset)], 0, self::SHOWN);
    }

    // A CRUD an admin fills that holds nothing yet - a list of what happened (see MenuProviderInterface's 'creatable') being empty says nothing about its feature
    private function isUnused(array $entry): bool
    {
        $controller = $entry['controller'] ?? null;
        if (null === $controller || false === ($entry['creatable'] ?? true) || !is_subclass_of($controller, AbstractCrudController::class)) {
            return false;
        }

        $entityFqcn = $controller::getEntityFqcn();

        return 0 === $this->entityManager->getRepository($entityFqcn)->count([]);
    }
}
