<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Twig;

use c975L\ConfigBundle\Management\FeedRenderer;
use Twig\Attribute\AsTwigFunction;

// Hands layout.html.twig the feeds to announce with a <link rel="alternate" type="application/atom+xml">
class FeedExtension
{
    public function __construct(private readonly FeedRenderer $feedRenderer)
    {
    }

    // Feeds holding at least one entry, as name => title
    #[AsTwigFunction('feeds')]
    public function getFeeds(): array
    {
        return $this->feedRenderer->available();
    }
}
