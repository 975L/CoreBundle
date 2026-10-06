<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// Implemented by whatever publishes the films of the guided projects on the site itself (SiteBundle's tutorials): the "Watch the film" link of a project then leads to the site's own film. A project none of them answers for keeps the ecosystem's film (see EcosystemUrls::TUTORIAL_FILM), which is where the bundles' own projects are filmed
interface TutorialFilmUrlProviderInterface
{
    // Where this site shows the film of the given project, null when it has none of its own
    public function getFilmUrl(string $slug): ?string;

    // The urls of a film only the back office shows ("video", "subtitles", "poster"), its "locale" and whether it is "narrated", played right in the projects' page rather than linked - null for a film shown publicly, which keeps its link
    /** @return ?array{video: string, subtitles: string, poster: string, locale: string, narrated: bool} */
    public function getFilmPlayer(string $slug): ?array;
}
