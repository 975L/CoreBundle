<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigDeclarationLocator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

// The features a config entry switches on ("feature": true in configs.json) whose entry is still empty - an option turned off is a choice and never flagged, only a key nobody has filled in
class UnusedConfigFeatureReader
{
    public function __construct(
        private readonly ConfigDeclarationLocator $declarationLocator,
        private readonly ConfigRepository $configRepository,
        private readonly ConfigLabelResolver $configLabelResolver,
        private readonly ConfigEntryLink $configEntryLink,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
    ) {
    }

    // [{label, description, url}], one per empty feature entry the current user may edit, in the order the entries are labelled
    public function getFeatures(): array
    {
        $slugs = $this->declarationLocator->findFeatureSlugs();
        if ([] === $slugs) {
            return [];
        }

        $features = [];
        foreach ($this->configRepository->findBy(['slug' => $slugs], ['label' => 'ASC']) as $config) {
            if ('' !== (string) $config->getValue() || !$this->security->isGranted($this->configEntryLink->role($config))) {
                continue;
            }

            $features[] = [
                'label' => $this->configLabelResolver->resolve($config),
                'description' => $config->getDescription() ? $this->translator->trans($config->getDescription(), [], 'site_config') : '',
                'url' => $this->configEntryLink->editUrl($config),
            ];
        }

        return $features;
    }
}
