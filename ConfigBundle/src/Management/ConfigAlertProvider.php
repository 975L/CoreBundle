<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Controller\Management\ConfigCrudController;
use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Alerts for configs still missing a value despite being flagged with a severity, plus the sensitive ones filled but unreadable
class ConfigAlertProvider implements AlertProviderInterface
{
    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigLabelResolver $configLabelResolver,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getAlerts(): array
    {
        $alerts = [];

        foreach ($this->configRepository->findRequiringAttention() as $config) {
            $alerts[] = [
                // Resolved here rather than left as a key for the template: the label of an app's own config is often stored in clear, and the template's trans() has no way of falling back to it
                'label' => $this->configLabelResolver->resolve($config),
                'description' => $config->getDescription(),
                'severity' => $config->getSeverity(),
                // Its screen is the admin's own (see ConfigCrudController) - the dashboard now renders for an editor, who would read an alert about a setting they cannot open
                'role' => $this->configService->get('site-role-admin'),
                'url' => $this->editUrl($config),
            ];
        }

        // A value ConfigService could not decrypt is left empty for everything reading it, while the entry still shows as filled everywhere - the site then behaves as if the setting had never been given (an api key silently gone, see ConfigService::loadAll()) and nothing said so
        foreach ($this->configRepository->findSensitiveWithValue() as $config) {
            if ('' !== (string) $this->configService->get($config->getSlug())) {
                continue;
            }

            $alerts[] = [
                'label' => $this->configLabelResolver->resolve($config),
                'description' => $this->translator->trans('description.config_unreadable', [], 'config'),
                'severity' => Config::SEVERITY_DANGER,
                'role' => $this->configService->get('site-role-admin'),
                'url' => $this->editUrl($config),
            ];
        }

        return $alerts;
    }

    private function editUrl(Config $config): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(ConfigCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($config->getId())
            ->generateUrl();
    }
}
