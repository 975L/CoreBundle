<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Service\JsonLdBuilder;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

// The structured data a template assembles itself - a block's FAQPage, a site's own breadcrumb - encoded the one way every bundle's graph is (see JsonLdBuilder)
class JsonLdExtension
{
    public function __construct(private readonly JsonLdBuilder $jsonLdBuilder)
    {
    }

    // Returns the <script type="application/ld+json"> payload of a graph built in Twig, empty when there is nothing to publish
    #[AsTwigFunction('json_ld', isSafe: ['html'])]
    public function jsonLd(array $snippet): string
    {
        return $this->jsonLdBuilder->encode($snippet);
    }

    // Returns the <script type="application/ld+json"> payload of a trail of {name, url} levels, the page's own last, empty when it leads nowhere
    #[AsTwigFunction('breadcrumb_json_ld', isSafe: ['html'])]
    public function breadcrumbJsonLd(array $trail): string
    {
        return $this->jsonLdBuilder->encode($this->jsonLdBuilder->breadcrumb($trail));
    }

    // One line of plain text out of a rich field, as a graph carries it
    #[AsTwigFilter('json_ld_text')]
    public function plainText(mixed $html): string
    {
        return $this->jsonLdBuilder->plainText($html);
    }
}
