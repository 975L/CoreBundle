<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

// An address a visitor may be sent to from a link an editor typed: nothing a browser would run or open on the site's own origin (javascript:, data:, vbscript:, file:...). A relative url, a path, an anchor or a query go through, as do the "page:12#anchor" and "route:shop_index" targets a link field stores, which are decoded server-side and reach no browser as such (see LinkTargetType)
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class SafeUrl extends Constraint
{
    public const array SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public string $message = 'label.url_scheme_not_allowed';

    #[HasNamedArguments]
    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
