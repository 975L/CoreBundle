<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\MediaUsageProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Baseline usage info for any Media attached to a Block: UiBundle knows the Block, but not which entity/bundle owns it (see SiteBundle's own MediaUsageProviderInterface implementation for that)
class BlockMediaUsageProvider implements MediaUsageProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getUsages(array $medias): array
    {
        $usages = [];

        foreach ($medias as $media) {
            $block = $media->getBlock();
            if (null === $block) {
                continue;
            }

            $usages[$media->getId()][] = [
                'label' => $this->translator->trans('label.attached_to_block', [
                    '%id%' => (string) $block->getId(),
                    '%kind%' => $block->getLabel() ?? (string) $block->getKind(),
                ], 'ui'),
                'url' => null,
            ];
        }

        return $usages;
    }
}
