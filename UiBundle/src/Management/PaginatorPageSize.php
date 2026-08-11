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

// EasyAdmin fixes how many rows a list shows server-side (Crud::setPaginatorPageSize) and ships no way for an admin to change it - 20 rows are plenty for a table of redirects, far too few for a gallery of media. This reads that choice from a "pageSize" query parameter instead, which management/paginator.html.twig offers as plain links under the paginator. Wired once for every CRUD of every c975L bundle in ConfigBundle's DashboardController::configureCrud(), the single Crud config each controller's own inherits from. Nothing else has to carry the choice around: AdminUrlGenerator rebuilds every admin url from the current request's query parameters, so the pagination, sorting and filtering links keep it on their own.
class PaginatorPageSize
{
    // A whitelist, not a range: "pageSize" comes straight from the url, where anyone could otherwise ask for a LIMIT of a million rows
    public const SIZES = [20, 50, 100];

    public const DEFAULT_SIZE = 20;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    // Read through query->all() rather than query->getInt(): a forged "?pageSize[]=20" makes InputBag::get() throw a 400, where an unknown value should simply fall back to the default
    public function resolve(): int
    {
        $requested = $this->requestStack->getCurrentRequest()?->query->all()['pageSize'] ?? null;

        return \in_array($requested, array_map('strval', self::SIZES), true) ? (int) $requested : self::DEFAULT_SIZE;
    }
}
