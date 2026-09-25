<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// The c975L ecosystem's own pages the back office links out to - the same for every site, hence constants rather than config entries nobody would ever change
final class EcosystemUrls
{
    // Every film, one page per bundle
    public const string TUTORIALS = 'https://bundles.975l.com/tutoriels';

    // Followed by a guided project's slug, answering one never filmed too by sending it on to the index (see GuidedProjectBuilder)
    public const string TUTORIAL_FILM = self::TUTORIALS . '/film';

    // Every block kind of every bundle, each with a live preview
    public const string BLOCK_SHOWCASE = 'https://bundles.975l.com/pages/blocks';
}
