<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Twig\Attribute\AsTwigFunction;

// The meta tags a third-party service asks for to prove the site's owner holds its account (Pinterest, Meta, Bing…), read from one key so a new service needs no code - Google is better proved by a DNS record, which covers the whole domain
class DomainVerificationExtension
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // "name=value" pairs separated by commas, as name => value - an entry missing either side is skipped rather than printed as a broken tag
    #[AsTwigFunction('domain_verifications')]
    public function getVerifications(): array
    {
        $verifications = [];
        foreach (explode(',', (string) $this->configService->get('ui-domain-verifications')) as $entry) {
            [$name, $value] = array_map(trim(...), explode('=', $entry, 2)) + [1 => ''];
            if ('' !== $name && '' !== $value) {
                $verifications[$name] = $value;
            }
        }

        return $verifications;
    }
}
