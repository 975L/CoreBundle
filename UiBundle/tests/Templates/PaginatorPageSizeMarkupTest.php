<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Management\PaginatorPageSize;
use c975L\UiBundle\Twig\PageSizeExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFunction;

// This template replaces EasyAdmin's own paginator for every CRUD at once (see ConfigBundle's DashboardController::configureCrud), so the links it writes are the only way an admin ever reaches another page size
class PaginatorPageSizeMarkupTest extends TestCase
{
    // The size in use is stated rather than linked, a link back to the page already shown being no choice at all
    public function testTheThreeSizesAreOfferedAndTheCurrentOneIsNotALink(): void
    {
        $html = $this->render(500, 50);

        $this->assertStringContainsString('<strong class="mx-1" aria-current="true">50</strong>', $html);
        $this->assertStringContainsString('>20</a>', $html);
        $this->assertStringContainsString('>100</a>', $html);
    }

    // Page 7 of a 20-row list is out of range once 100 rows are shown, so each link goes back to page 1 - the rest of the query string (sorting, filtering) is carried by ea_url() on its own
    public function testALinkCarriesItsSizeAndGoesBackToTheFirstPage(): void
    {
        $html = $this->render(500, 20);

        $this->assertStringContainsString('href="/management?pageSize=100&amp;page=1"', $html);
    }

    // A list every size shows whole has nothing to offer: the choice only appears once the smallest one truncates it
    public function testNothingIsOfferedWhenTheSmallestSizeShowsEveryRow(): void
    {
        $this->assertStringNotContainsString('list-pagination-page-size', $this->render(PaginatorPageSize::DEFAULT_SIZE, 20));
    }

    // The sizes come from PageSizeExtension itself, the one list PaginatorPageSize also validates against
    private function render(int $numResults, int $pageSize): string
    {
        // EasyAdmin's own paginator is included by the template and plays no part in the sizes offered under it, so it is stubbed away
        $twig = new Environment(new ChainLoader([
            new ArrayLoader(['@EasyAdmin/crud/paginator.html.twig' => '<nav class="native-paginator"></nav>']),
            new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'),
        ]));
        // Untranslated keys come back as-is, which is enough for the links read above
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        // What TwigBundle assembles from the #[AsTwigFunction] attributes: the extension reads them, the runtime loader hands over the instance the callables are called on
        $twig->addExtension(new AttributeExtension(PageSizeExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            PageSizeExtension::class => static fn (): PageSizeExtension => new PageSizeExtension(),
        ]));
        $twig->addFunction(new TwigFunction('ea_url', static fn (array $parameters = []): string => '/management?' . http_build_query($parameters)));

        return $twig->render('management/paginator.html.twig', [
            'paginator' => ['numResults' => $numResults, 'pageSize' => $pageSize],
        ]);
    }
}
