<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

/**
 * One page of a listing: the items it holds, and what the page after it is reached with. Built by Paginator, never by hand - the route and the query it carries are the ones of the request it was built in.
 *
 * @template T
 *
 * @implements \IteratorAggregate<int, T>
 */
final class Pagination implements \IteratorAggregate, \Countable
{
    /**
     * @param T[]                  $items       the items of this page alone
     * @param array<string, mixed> $routeParams what the next page's url is rebuilt from - the route's own parameters and the query the visitor came with, so a search or a filter survives the jump
     */
    public function __construct(
        private readonly array $items,
        private readonly int $currentPage,
        private readonly int $perPage,
        private readonly int $total,
        private readonly string $route = '',
        private readonly array $routeParams = [],
    ) {
    }

    /** @return \ArrayIterator<int, T> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    // What this page holds, which is not the whole listing: "products|length" reads this, "products.getTotalItemCount" the other
    public function count(): int
    {
        return count($this->items);
    }

    public function getCurrentPageNumber(): int
    {
        return $this->currentPage;
    }

    public function getItemNumberPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotalItemCount(): int
    {
        return $this->total;
    }

    // At least one, so an empty listing still says "page 1 of 1" rather than "1 of 0"
    public function getPageCount(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    // The route this listing is served by, which the next page's link is built on - "pagination.route" in a template resolves here
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * The parameters the next page's url is built with, the given ones overriding what the current request carried.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public function query(array $overrides = []): array
    {
        return array_merge($this->routeParams, $overrides);
    }
}
