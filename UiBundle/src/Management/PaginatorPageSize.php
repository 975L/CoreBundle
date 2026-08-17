<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use Symfony\Component\HttpFoundation\RequestStack;

// EasyAdmin fixes how many rows a list shows server-side (Crud::setPaginatorPageSize) and ships no way for an admin to change it - 20 rows are plenty for a table of redirects, far too few for a gallery of media. This reads that choice from a "pageSize" query parameter instead, which management/paginator.html.twig offers as plain links under the paginator. Wired once for every CRUD of every c975L bundle in ConfigBundle's DashboardController::configureCrud(), the single Crud config each controller's own inherits from. The pagination, sorting and filtering links carry the parameter on their own, AdminUrlGenerator rebuilding every url from the current request's query parameters - but the action links (edit, delete, the clickable row) do not: ActionFactory::generateActionUrl() regenerates them through unsetAllExcept() with a closed whitelist of EasyAdmin's own parameters, and anything else is dropped. Editing a record and coming back would therefore always land on 20 rows again, hence the choice being remembered in the session as soon as it is made.
class PaginatorPageSize
{
    // A whitelist, not a range: "pageSize" comes straight from the url, where anyone could otherwise ask for a LIMIT of a million rows
    public const SIZES = [20, 50, 100];

    public const DEFAULT_SIZE = 20;

    // One value for every list, not one per CRUD: an admin choosing to see 100 rows is telling how much of a screen they want filled, not making a decision about a single entity
    private const string SESSION_KEY = 'c975l_ui.paginator_page_size';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    // Read through query->all() rather than query->getInt(): a forged "?pageSize[]=20" makes InputBag::get() throw a 400, where an unknown value should simply fall back to the default
    public function resolve(): int
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return self::DEFAULT_SIZE;
        }

        $requested = $request->query->all()['pageSize'] ?? null;
        $session = $request->hasSession() ? $request->getSession() : null;

        // The url wins whenever it carries a size, and that size becomes the one remembered - a rejected value never is, so a forged parameter cannot outlive its own request
        if (\in_array($requested, array_map(strval(...), self::SIZES), true)) {
            $session?->set(self::SESSION_KEY, (int) $requested);

            return (int) $requested;
        }

        $remembered = $session?->get(self::SESSION_KEY);

        return \in_array($remembered, self::SIZES, true) ? (int) $remembered : self::DEFAULT_SIZE;
    }
}
