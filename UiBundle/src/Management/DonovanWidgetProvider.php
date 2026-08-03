<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\DashboardWidgetProviderInterface;
use c975L\UiBundle\Contract\AiAssistantClientInterface;
use Symfony\Bundle\SecurityBundle\Security;

// No "not enabled yet" placeholder: the widget stays absent, AiAlertProvider being the nudge in that case
class DonovanWidgetProvider implements DashboardWidgetProviderInterface
{
    public function __construct(
        private readonly AiAssistantClientInterface $aiAssistantClient,
        private readonly Security $security,
    ) {
    }

    public function getDashboardWidgets(): array
    {
        if (!$this->aiAssistantClient->isEnabled() || !$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return [];
        }

        return [
            [
                'template' => '@c975LUi/management/_donovan_dashboard_widget.html.twig',
                'context' => [],
            ],
        ];
    }
}
