<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Model\Pagination;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\RequestStack;

// Cuts a listing into pages. A listing already read whole - every caller hands over an array, a page of books or of products being built from rows the repository has joined and sorted in php rather than from a query that could carry its own LIMIT. Replaces KnpPaginatorBundle, whose page links no listing renders any more: they all grow as the visitor scrolls (see UiBundle's infinite-scroll.js), and what is left of a paginator here is the slice and the url of the page after it
class Paginator
{
    // The query parameter the page is read from, short because it is shared and visible in every listing's url
    public const string PAGE_PARAMETER = 'p';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @template T
     *
     * @param T[] $items every item of the listing, the page being cut here
     *
     * @return Pagination<T>
     */
    public function paginate(array $items, int $page, int $perPage): Pagination
    {
        $page = max(1, $page);
        $request = $this->requestStack->getCurrentRequest();

        return new Pagination(
            array_slice($items, ($page - 1) * $perPage, $perPage),
            $page,
            $perPage,
            count($items),
            (string) $request?->attributes->get('_route', ''),
            // The route's own parameters as well as the query: a listing served under "/serie/{slug}" would otherwise rebuild its next page's url without the slug it is read under, and path() would refuse to generate it
            array_merge(
                (array) $request?->attributes->get('_route_params', []),
                $request?->query->all() ?? []
            ),
        );
    }

    // The page a request asks for, 1 when it asks for none or for something that is not a page number
    public function getPage(InputBag $query): int
    {
        return max(1, (int) $query->get(self::PAGE_PARAMETER));
    }
}
