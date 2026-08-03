<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Symfony\Component\String\Slugger\SluggerInterface;

// Normalizes a raw slug (accents, spaces, case) and appends -2, -3... until $collides() reports the candidate free - what every bundle with a slugged entity needs, whatever the scope its uniqueness is checked against. Static and stateless rather than the trait this used to be in SiteBundle: a trait shared across bundles is only ever analysed against the users living in the same package
class UniqueSlug
{
    // $collides receives each candidate and answers whether it is already taken - site-wide for a Page, scoped to its own group for a CollectionItem, to its category for a Product... the scope is the caller's business, the suffixing algorithm is fixed here once
    public static function build(SluggerInterface $slugger, string $base, callable $collides): string
    {
        $slug = strtolower($slugger->slug($base)->toString());
        $candidate = $slug;
        for ($i = 2; $collides($candidate); ++$i) {
            $candidate = $slug . '-' . $i;
        }

        return $candidate;
    }
}
