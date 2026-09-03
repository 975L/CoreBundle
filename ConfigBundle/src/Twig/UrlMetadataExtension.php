<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Twig;

use c975L\ConfigBundle\Entity\UrlMetadata;
use c975L\ConfigBundle\Service\UrlMetadataResolver;
use Twig\Attribute\AsTwigFunction;

// Hands the layouts what the url being rendered says of itself, for the pages no entity answers for - a listing, a filtered listing, a tool page (see UrlMetadata). Both layouts call it last, after whatever the rendering template set: an entity always speaks first, and a row here only fills a silence
class UrlMetadataExtension
{
    public function __construct(private readonly UrlMetadataResolver $urlMetadataResolver)
    {
    }

    // Null when nothing was written for this url, which is the normal state of a site whose listings have not been described yet - the layouts then emit no more than they did before, and the content quality health check is what says so.
    // The path argument is for the template serving several urls where only one of them is described: a stepped form, a paginated listing, a wizard - it names the url the row was written for, ideally through path() rather than as a literal. Left out, the row of the page being rendered is returned
    #[AsTwigFunction('url_metadata')]
    public function getUrlMetadata(?string $path = null): ?UrlMetadata
    {
        return null === $path
            ? $this->urlMetadataResolver->forCurrentRequest()
            : $this->urlMetadataResolver->forPath($path);
    }

    // What a listing sets its own title from. The layouts only read a row for what the rendering template left unsaid, so a listing setting its translated label unconditionally made its own row unreachable - it hands that label here instead, and what an editor wrote wins over it
    #[AsTwigFunction('url_metadata_title')]
    public function getTitle(string $fallback, ?string $path = null): string
    {
        return $this->getUrlMetadata($path)?->getTitle() ?: $fallback;
    }

    // The same for the sentence a listing is shared and indexed with
    #[AsTwigFunction('url_metadata_summary')]
    public function getSummary(string $fallback, ?string $path = null): string
    {
        return $this->getUrlMetadata($path)?->getSummarySocialNetwork() ?: $fallback;
    }
}
