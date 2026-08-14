<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Management\HealthCheckAdviceBuilder;
use c975L\ConfigBundle\Management\HealthCheckAdviceProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// What to do about the "legal_model" rows LegalModelDriftHealthCheckProvider writes. The row names the sections that moved; what it cannot say is that there is a decision to take and that nobody will take it in the reader's place - the bundle never merges its new wording into a passage the site rewrote, so a row left alone stays exactly as it is, for good
class LegalModelDriftHealthCheckAdviceProvider implements HealthCheckAdviceProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildAdvice(array $results): array
    {
        $advice = [];

        foreach ($results as $result) {
            // Every row of this kind is a drifted one - the check reports nothing at all for a document that didn't move - and every one of them is STATUS_OK, drift being news rather than a fault
            if (LegalModelDriftHealthCheckProvider::KIND !== $result->getKind()) {
                continue;
            }

            // No url: the row's own edit link already opens the customization screen, which shows the drift section by section
            $advice[HealthCheckAdviceBuilder::key($result)] = [[
                'text' => $this->translator->trans('label.health_check_advice_legal_model_drift', [], 'ui'),
                'url' => null,
            ]];
        }

        return $advice;
    }
}
