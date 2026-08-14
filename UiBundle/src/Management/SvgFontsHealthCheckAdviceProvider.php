<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckAdviceBuilder;
use c975L\ConfigBundle\Management\HealthCheckAdviceProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// What to do about the "svg-fonts" rows SvgFontsHealthCheckProvider writes. The row itself names the file and the fonts it depends on, and says to vectorize - which is the verdict, not the way out: whoever reads it in the back office is rarely the person who drew the file, and "convert it to paths" is a menu entry they have never opened. The lines below name that menu entry, and say why the problem is worse than the row makes it sound
class SvgFontsHealthCheckAdviceProvider implements HealthCheckAdviceProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildAdvice(array $results): array
    {
        $advice = [];

        foreach ($results as $result) {
            // The OK rows are what lets a corrected file go back to green, and they need no advice at all: a vectorized file has nothing left to do
            if (SvgFontsHealthCheckProvider::KIND !== $result->getKind() || HealthCheckResult::STATUS_WARNING !== $result->getStatus()) {
                continue;
            }

            $advice[HealthCheckAdviceBuilder::key($result)] = [
                $this->line('label.health_check_advice_svg_fonts_vectorize'),
                $this->line('label.health_check_advice_svg_fonts_served_as_image'),
            ];
        }

        return $advice;
    }

    // No url on either line: the row already carries the edit link to the media, and the work itself happens in a drawing tool, nowhere this site could send anyone
    private function line(string $translationId): array
    {
        return [
            'text' => $this->translator->trans($translationId, [], 'ui'),
            'url' => null,
        ];
    }
}
