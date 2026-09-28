<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Service\JsonLdBuilder;
use c975L\UiBundle\Twig\JsonLdExtension;
use PHPUnit\Framework\TestCase;

class JsonLdExtensionTest extends TestCase
{
    private function extension(): JsonLdExtension
    {
        return new JsonLdExtension(new JsonLdBuilder());
    }

    // Encoded like every other graph, a typed "</script>" unable to close the tag
    public function testJsonLdEncodesTheGraphSafely(): void
    {
        $json = $this->extension()->jsonLd(['@type' => 'Thing', 'name' => '</script>']);

        $this->assertSame('{"@type":"Thing","name":"\\u003C/script\\u003E"}', $json);
    }

    public function testJsonLdPublishesNothingForAnEmptyGraph(): void
    {
        $this->assertSame('', $this->extension()->jsonLd([]));
    }

    public function testBreadcrumbJsonLdEncodesATrail(): void
    {
        $json = $this->extension()->breadcrumbJsonLd([['name' => 'Home', 'url' => 'https://a.test/'], ['name' => 'Page', 'url' => 'https://a.test/page']]);

        $this->assertStringContainsString('"@type":"BreadcrumbList"', $json);
        $this->assertStringContainsString('"name":"Page"', $json);
    }

    // A single level is the page itself, a trail leading nowhere
    public function testBreadcrumbJsonLdPublishesNothingForASingleLevel(): void
    {
        $this->assertSame('', $this->extension()->breadcrumbJsonLd([['name' => 'Home', 'url' => 'https://a.test/']]));
    }

    public function testPlainTextStripsTheMarkup(): void
    {
        $this->assertSame('Bold text', $this->extension()->plainText('<strong>Bold</strong> text'));
    }
}
