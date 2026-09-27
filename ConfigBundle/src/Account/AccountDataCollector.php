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

// Gathers what every AccountDataProviderInterface keeps about a member into one export
class AccountDataCollector
{
    /** @param iterable<AccountDataProviderInterface> $providers */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    // Every provider's part, in the order they came, dates written ISO 8601
    /** @return array<string, mixed> */
    public function collect(UserInterface $user): array
    {
        $data = [];
        foreach ($this->providers as $provider) {
            $data += $provider->getAccountData($user);
        }

        array_walk_recursive($data, static function (mixed &$value): void {
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(\DateTimeInterface::ATOM);
            }
        });

        return $data;
    }
}
