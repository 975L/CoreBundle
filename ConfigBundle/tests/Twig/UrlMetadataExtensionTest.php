<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Twig;

use c975L\ConfigBundle\Entity\UrlMetadata;
use c975L\ConfigBundle\Service\UrlMetadataResolver;
use c975L\ConfigBundle\Twig\UrlMetadataExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AttributeExtension;

class UrlMetadataExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersUrlMetadataFunction(): void
    {
        $functions = new AttributeExtension(UrlMetadataExtension::class)->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('url_metadata', $functions[0]->getName());
    }

    // Called with nothing, the function answers for the page being rendered - what both layouts do
    public function testWithoutAPathTheRowOfThePageBeingRenderedIsReturned(): void
    {
        $urlMetadata = new UrlMetadata()->setPath('/animaux');

        $resolver = $this->createMock(UrlMetadataResolver::class);
        $resolver->expects($this->once())->method('forCurrentRequest')->willReturn($urlMetadata);
        $resolver->expects($this->never())->method('forPath');

        $this->assertSame($urlMetadata, new UrlMetadataExtension($resolver)->getUrlMetadata());
    }

    // A template serving several urls names the one the row was written for
    public function testAPathAsksForThatVeryUrl(): void
    {
        $urlMetadata = new UrlMetadata()->setPath('/animaux');

        $resolver = $this->createMock(UrlMetadataResolver::class);
        $resolver->expects($this->once())->method('forPath')->with('/animaux')->willReturn($urlMetadata);
        $resolver->expects($this->never())->method('forCurrentRequest');

        $this->assertSame($urlMetadata, new UrlMetadataExtension($resolver)->getUrlMetadata('/animaux'));
    }

    // The normal state of a site whose listings have not been described yet: the layouts then emit no more than they did before
    public function testNullWhenNothingWasWrittenForThatUrl(): void
    {
        $resolver = $this->createStub(UrlMetadataResolver::class);
        $resolver->method('forCurrentRequest')->willReturn(null);
        $resolver->method('forPath')->willReturn(null);

        $extension = new UrlMetadataExtension($resolver);

        $this->assertNull($extension->getUrlMetadata());
        $this->assertNull($extension->getUrlMetadata('/animaux'));
    }
}
