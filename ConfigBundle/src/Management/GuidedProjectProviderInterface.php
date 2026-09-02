<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

interface GuidedProjectProviderInterface
{
    /**
     * Unlike an essential action, a project walks the user through a real task to carry out (create a page, add a block, put it in a menu) - so it carries no "isDone" and nothing is ever derived from the site's own data: it's a replayable exercise, still worth following on a site already holding the content it teaches to create, and still worth replaying once done. "order" decides the display order across every provider (low to high), a deliberate sequence rather than an alphabetical one - the same one the user is meant to follow. Each bundle owns a thousand-block, stated here once rather than recopied into every provider's own header - the seven copies that lived there had drifted apart, two of them naming ranges their neighbours had long overrun: ConfigBundle 1000, SiteBundle 2000, UiBundle 3000, SocialBundle 4000, GalleryBundle 5000, BookBundle 6000, PaymentBundle 7000, ShopBundle 8000, CrowdfundingBundle 9000, PurchaseCreditsBundle 10000. Projects run at a step of 10 inside their own block, which leaves 99 slots to slip a new one where it belongs rather than appending it at the end. A step sets either "url", sending the user to another screen (the panel picks the parcours back up there after the page load), or "highlight", a CSS selector pointing at what to look at on the screen already open - never both, the first leaving the page the second points into. "slug" must be unique across every bundle contributing projects. "narration" is what the step sounds like when it is spoken rather than read - a full sentence naming where to look and what to do ("ouvrez le menu Pages, dans la colonne de gauche"), where "label" is the caption of a panel someone is already looking at. It is what the films of the back office say (see app:video:narrate in bundles.975l.com) and it also sizes the step: a screen is held long enough for its sentence to be said, not read. Optional, the label and the description standing in for it when it is missing, which reads as an interface caption spoken aloud. It is resolved in a domain of its own, the project's own "translation_domain" suffixed with "_narration" (a project served by "site" reads its narrations in "site_narration"): they are spoken and never drawn, and they are written in French and English alone where the rest of the bundle also speaks Spanish.
     *
     * @return list<array{slug: string, label: string, description?: ?string, translation_domain: string, order: int, role?: ?string, steps: list<array{label: string, description?: ?string, narration?: ?string, url?: ?string, highlight?: ?string}>}>
     */
    public function getGuidedProjects(): array;
}
