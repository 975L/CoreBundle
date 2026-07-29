<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Management\AlertProviderInterface;
use c975L\UiBundle\Contract\AiAssistantClientInterface;
use c975L\UiBundle\Service\AiRephraseClient;
use c975L\UiBundle\Service\AiUsageTracker;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Two setup nudges keyed on the same isEnabled() the page itself reads, plus a warning while the last rephrase failed
class AiAlertProvider implements AlertProviderInterface
{
    public function __construct(
        private readonly AiUsageTracker $aiUsageTracker,
        private readonly AiAssistantClientInterface $aiAssistantClient,
        private readonly AiRephraseClient $aiRephraseClient,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getAlerts(): array
    {
        $alerts = [];

        if (!$this->aiAssistantClient->isEnabled()) {
            $alerts[] = $this->alert(
                'label.ai_assistant_dashboard_not_enabled',
                'description.ai_assistant_dashboard_not_enabled',
                Config::SEVERITY_INFO,
            );
        }

        if (!$this->aiRephraseClient->isEnabled()) {
            $alerts[] = $this->alert(
                'label.ai_rephrase_not_enabled',
                'description.ai_rephrase_not_enabled',
                Config::SEVERITY_INFO,
            );
        }

        $usage = $this->aiUsageTracker->getCurrentMonth();
        if ($usage && $usage->getLastFailureAt()) {
            $alerts[] = $this->alert(
                'label.ai_rephrase_failure_alert',
                'description.ai_rephrase_failure_alert',
                Config::SEVERITY_WARNING,
            );
        }

        return $alerts;
    }

    private function alert(string $label, string $description, string $severity): array
    {
        return [
            'label' => $label,
            'description' => $description,
            'severity' => $severity,
            'url' => $this->urlGenerator->generate('management_ui_ai_assistant_index'),
        ];
    }
}
