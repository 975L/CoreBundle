<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

// Dependency-free on purpose, same reading as RatingExtension: the queries behind ReviewRuntime are only built the first time a template actually asks for a review, not on every request that merely boots Twig
class ReviewExtension extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ui_reviews', [ReviewRuntime::class, 'reviews']),
            new TwigFunction('ui_reviews_enabled', [ReviewRuntime::class, 'reviewsEnabled']),
        ];
    }
}
