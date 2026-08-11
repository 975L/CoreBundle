<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

// What the "Made by"/"Hosted by" credits of a footer show - "display-made-by" and "display-hosted-by" are a choice between the four modes below, read through here rather than directly so the legacy value a site upgraded from the bool era still holds is understood in one place
class CreditsExtension extends AbstractExtension
{
    public const MODE_NONE = 'none';
    public const MODE_LOGO = 'logo';
    public const MODE_NAME = 'name';
    public const MODE_LOGO_NAME = 'logo-name';

    // The same list, in the same order, as the "choices" both entries declare in config/configs.json
    public const MODES = [
        self::MODE_NONE,
        self::MODE_LOGO,
        self::MODE_NAME,
        self::MODE_LOGO_NAME,
    ];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('credits_mode', $this->getCreditsMode(...)),
        ];
    }

    // The mode a "display-*-by" config asks for, always one of MODES
    public function getCreditsMode(string $slug): string
    {
        $value = $this->configService->get($slug);

        if (is_string($value) && in_array($value, self::MODES, true)) {
            return $value;
        }

        // Both entries were a "bool" until 10/08/2026, and the value of an existing row is never rewritten by c975l:config:load-all (see ConfigService::syncMetadata) - a site upgraded but not re-configured yet still holds true/false, read as a real bool before that command turns the kind into a choice and as the raw string afterwards. Either way it means the logo, the only thing these credits could show back then
        return $this->configService->getBool($value) ? self::MODE_LOGO : self::MODE_NONE;
    }
}
