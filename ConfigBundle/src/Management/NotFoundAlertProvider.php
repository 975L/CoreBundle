<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Controller\Management\NotFoundCrudController;
use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\NotFoundRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Alerts on the site's own broken links. Only the internal ones: a stale link another site publishes is worth a redirect when convenient, a page of ours sending its readers to a 404 is worth seeing on the dashboard the same day - which is the whole point of recording them, a screen nobody thinks to open being no better than the silence Monolog already keeps on 404s
class NotFoundAlertProvider implements AlertProviderInterface
{
    public function __construct(
        private readonly NotFoundRepository $notFoundRepository,
        private readonly ConfigServiceInterface $configService,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getAlerts(): array
    {
        $count = $this->notFoundRepository->countInternal();
        if (0 === $count) {
            return [];
        }

        return [[
            'label' => $this->translator->trans('label.not_found_alert', ['%count%' => $count], 'config'),
            'description' => $this->translator->trans('description.not_found_alert', [], 'config'),
            // A broken link costs readers, not the site: worth today, not worth an interruption
            'severity' => Config::SEVERITY_WARNING,
            // The same bar as its screen and as redirects (see NotFoundCrudController) - a link that broke is the editor's business
            'role' => $this->configService->get('site-role-editor'),
            'url' => $this->adminUrlGenerator
                ->unsetAll()
                ->setController(NotFoundCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl(),
        ]];
    }
}
