<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\GuidedProjectKeyGenerator;
use c975L\ConfigBundle\Management\PageOverlayProviderInterface;
use c975L\UiBundle\Contract\AiAssistantClientInterface;
use Symfony\Bundle\SecurityBundle\Security;

// A panel over every admin page rather than a dashboard card: a reader follows an answer through the menu, and the answer has to stay in sight while they do. No "not enabled yet" placeholder: the panel stays absent, AiAlertProvider being the nudge in that case
class DonovanWidgetProvider implements PageOverlayProviderInterface
{
    public function __construct(
        private readonly AiAssistantClientInterface $aiAssistantClient,
        private readonly Security $security,
        private readonly GuidedProjectKeyGenerator $keyGenerator,
    ) {
    }

    // The key scopes the conversation kept in sessionStorage to the account, as GuidedProjectKeyGenerator already does for the guided-project panel
    public function getPageOverlays(): array
    {
        if (!$this->aiAssistantClient->isEnabled() || !$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return [];
        }

        return [
            [
                'template' => '@c975LUi/management/_donovan_panel.html.twig',
                'context' => ['key' => $this->keyGenerator->getKey()],
            ],
        ];
    }
}
