<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

// The media library is a gallery of thumbnails, each one a link (see media_index.html.twig). Where that link goes used to be left entirely to EasyAdmin's default row action, which - Edit being hidden for a site-wide graphic - sent those to a detail page showing neither the image nor a single action. This reads the very expression that ships, the template extending EasyAdmin's own index and so not renderable on its own
class MediaGalleryLinkTest extends TestCase
{
    private function openingTag(): string
    {
        $template = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/management/media_index.html.twig');

        $this->assertSame(
            1,
            preg_match('/(\{% set siteGraphicUrl .*?<a.*?class="management-media-grid__item.*?>)/s', $template, $matches),
            'The gallery thumbnail link is no longer written as a url set right before its own tag, this test can no longer read it.'
        );

        return $matches[1];
    }

    private function render(array $context): string
    {
        return trim(new Environment(new ArrayLoader(['link' => $this->openingTag()]))->render('link', $context));
    }

    // A site-wide graphic (role set) is read-only here: its thumbnail opens SiteGraphicCrudController, the only screen editing it
    public function testASiteGraphicLinksToItsOwnEditScreen(): void
    {
        $tag = $this->render([
            'media' => ['id' => 7],
            'entity' => ['defaultActionUrl' => null],
            'site_graphic_urls' => [7 => '/management/site-graphic/edit?entityId=7'],
        ]);

        $this->assertSame('<a href="/management/site-graphic/edit?entityId=7" class="management-media-grid__item management-media-grid__item--site-graphic">', $tag);
    }

    // A block media is edited from the library itself, so it keeps EasyAdmin's own default row action
    public function testABlockMediaKeepsTheDefaultRowAction(): void
    {
        $tag = $this->render([
            'media' => ['id' => 8],
            'entity' => ['defaultActionUrl' => '/management/media/edit?entityId=8'],
            'site_graphic_urls' => [7 => '/management/site-graphic/edit?entityId=7'],
        ]);

        $this->assertSame('<a href="/management/media/edit?entityId=8" class="management-media-grid__item">', $tag);
    }

    // Nothing to open: an admin who may not edit the site graphics gets no url for them (see MediaCrudController::siteGraphicUrls()), and their Edit action being hidden here leaves EasyAdmin's own default row action empty too. The thumbnail stays visible, as plain content rather than as a link landing on a 403
    public function testAThumbnailWithNoUrlIsNotALinkAtAll(): void
    {
        $tag = $this->render([
            'media' => ['id' => 7],
            'entity' => ['defaultActionUrl' => null],
            'site_graphic_urls' => [],
        ]);

        $this->assertSame('<a class="management-media-grid__item">', $tag);
    }

    // The guided project sends the user to the media library then points at "alt" and "credits", two fields SiteGraphicCrudController's form does not hold - so it excludes the thumbnails opening it, by the very modifier written above. Renaming one without the other leaves a step highlighting a link that walks away from the parcours
    public function testTheGuidedProjectExcludesTheSiteGraphicThumbnails(): void
    {
        $tag = $this->render([
            'media' => ['id' => 7],
            'entity' => ['defaultActionUrl' => null],
            'site_graphic_urls' => [7 => '/management/site-graphic/edit?entityId=7'],
        ]);

        $this->assertStringContainsString('management-media-grid__item--site-graphic', $tag);
        $this->assertStringContainsString(
            ':not(.management-media-grid__item--site-graphic)',
            (string) file_get_contents(\dirname(__DIR__, 2) . '/src/Management/UiGuidedProjectProvider.php')
        );
    }
}
