<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\Paginator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PaginatorTest extends TestCase
{
    public function testThePageHoldsItsOwnSliceOfTheListing(): void
    {
        $pagination = $this->paginator()->paginate(range(1, 25), 2, 10);

        $this->assertSame(range(11, 20), iterator_to_array($pagination));
        $this->assertCount(10, $pagination);
        $this->assertSame(2, $pagination->getCurrentPageNumber());
        $this->assertSame(10, $pagination->getItemNumberPerPage());
        $this->assertSame(25, $pagination->getTotalItemCount());
        $this->assertSame(3, $pagination->getPageCount());
    }

    // The last page holds what is left of the listing, and asking for one past it holds nothing rather than erroring
    public function testAPageBeyondTheListingIsEmpty(): void
    {
        $this->assertCount(5, $this->paginator()->paginate(range(1, 25), 3, 10));
        $this->assertCount(0, $this->paginator()->paginate(range(1, 25), 9, 10));
    }

    // An empty listing is still one page: "1 / 0" is not a page number a listing can print
    public function testAnEmptyListingCountsOnePage(): void
    {
        $pagination = $this->paginator()->paginate([], 1, 10);

        $this->assertSame(0, $pagination->getTotalItemCount());
        $this->assertSame(1, $pagination->getPageCount());
    }

    // A page number below the first one is the first one: the url is a visitor's to write
    public function testAPageBelowOneIsTheFirstPage(): void
    {
        $pagination = $this->paginator()->paginate(range(1, 25), -3, 10);

        $this->assertSame(1, $pagination->getCurrentPageNumber());
        $this->assertSame(range(1, 10), iterator_to_array($pagination));
    }

    // What the next page's link is rebuilt from: the route's own parameters as much as the query, a listing served under "/serie/{slug}" having no url without its slug
    public function testTheNextPagesUrlKeepsTheRouteParametersAndTheQuery(): void
    {
        $request = new Request(['character' => 'zoe'], [], ['_route' => 'serie_display', '_route_params' => ['slug' => 'les-triados']]);

        $pagination = new Paginator(new RequestStack([$request]))->paginate(range(1, 25), 1, 10);

        $this->assertSame('serie_display', $pagination->getRoute());
        $this->assertSame(['slug' => 'les-triados', 'character' => 'zoe'], $pagination->query());
        $this->assertSame(['slug' => 'les-triados', 'character' => 'zoe', 'p' => 2], $pagination->query(['p' => 2]));
    }

    // Nothing to build an url from outside a request - a listing paginated from a command, a test - rather than a failure
    public function testWithoutARequestTheListingIsStillCut(): void
    {
        $pagination = new Paginator(new RequestStack())->paginate(range(1, 25), 1, 10);

        $this->assertCount(10, $pagination);
        $this->assertSame('', $pagination->getRoute());
        $this->assertSame([], $pagination->query());
    }

    public function testThePageIsReadFromTheQuery(): void
    {
        $paginator = $this->paginator();

        $this->assertSame(3, $paginator->getPage(new InputBag(['p' => '3'])));
        $this->assertSame(1, $paginator->getPage(new InputBag()));
        $this->assertSame(1, $paginator->getPage(new InputBag(['p' => 'deux'])));
        $this->assertSame(1, $paginator->getPage(new InputBag(['p' => '-2'])));
    }

    private function paginator(): Paginator
    {
        return new Paginator(new RequestStack([new Request()]));
    }
}
