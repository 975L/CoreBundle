<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Account;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Management\ProviderMerger;

// Merges the sections every AccountSectionProviderInterface contributes to the member's own page, in the order their positions say
class AccountSectionBuilder
{
    /** @param iterable<AccountSectionProviderInterface> $providers */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    // The sections of this member, the lowest position first, and in the order the providers came for an equal one (usort being stable)
    /** @return list<array{title: string, translation_domain: string, template: string, context: array<string, mixed>, position: int}> */
    public function getSections(UserInterface $user): array
    {
        $sections = array_map(
            static fn (array $section): array => $section + ['context' => [], 'position' => 0],
            ProviderMerger::merge($this->providers, static fn (AccountSectionProviderInterface $provider): array => $provider->getAccountSections($user)),
        );

        usort($sections, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return $sections;
    }
}
