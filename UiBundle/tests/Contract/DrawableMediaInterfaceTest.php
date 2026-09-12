<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Contract;

use c975L\UiBundle\Contract\DrawableMediaInterface;
use c975L\UiBundle\Entity\Media;
use PHPUnit\Framework\TestCase;

// The contract is only worth declaring while it says what the components really read: an application implements it instead of reading the templates, so a property a component starts reading without it being here is guesswork again, and one declared here and read nowhere is an entity made to answer for nothing
class DrawableMediaInterfaceTest extends TestCase
{
    // The components an application hands its own media to. The blocks are left out on purpose: they draw this bundle's own Media, bound to a Block, and read more of it than anything outside ever gets to
    private const array COMPONENTS = [
        'Slider/Slider.html.twig',
        'ImageCompare/ImageCompare.html.twig',
        'Text/Section.html.twig',
    ];

    public function testTheBundlesOwnMediaSatisfiesIt(): void
    {
        $this->assertInstanceOf(DrawableMediaInterface::class, new Media());
    }

    // Every value optional, so an entity with nothing but a file to show still draws rather than having to carry a caption and two sizes it has none of
    public function testAMediaCarryingNothingStillAnswersEveryGetter(): void
    {
        $media = new Media();

        $this->assertNull($media->getAlt());
        $this->assertNull($media->getMimeType());
        $this->assertNull($media->getLabel());
        $this->assertNull($media->getWidth());
        $this->assertNull($media->getHeight());
        $this->assertSame([], $media->getCssClasses());
        $this->assertFalse($media->isAbove());
    }

    // What the components read is what the contract declares - the drift this interface exists to stop
    public function testEveryPropertyTheComponentsReadIsDeclared(): void
    {
        $declared = $this->declaredProperties();

        foreach (self::COMPONENTS as $component) {
            foreach ($this->propertiesReadBy($component) as $property) {
                $this->assertContains(
                    $property,
                    $declared,
                    sprintf('"%s" reads "%s" off a media, which the contract declares no getter for - an application implementing it draws a page that fails on a visitor.', $component, $property)
                );
            }
        }
    }

    // And the other way round: a getter no component reads is an entity made to answer for nothing
    public function testEveryDeclaredPropertyIsReadBySomeComponent(): void
    {
        $read = [];
        foreach (self::COMPONENTS as $component) {
            $read = array_merge($read, $this->propertiesReadBy($component));
        }

        foreach ($this->declaredProperties() as $property) {
            $this->assertContains($property, $read, sprintf('No component reads "%s" off a media any more.', $property));
        }
    }

    /** @return string[] */
    private function declaredProperties(): array
    {
        $properties = [];
        foreach (new \ReflectionClass(DrawableMediaInterface::class)->getMethods() as $method) {
            // Twig reads "media.alt" through getAlt() as well as "media.above" through isAbove(), so the property is the name with its accessor's prefix taken off
            $properties[] = lcfirst((string) preg_replace('/^(get|is)/', '', $method->getName()));
        }

        return $properties;
    }

    /** @return string[] */
    private function propertiesReadBy(string $component): array
    {
        $template = __DIR__ . '/../../templates/components/' . $component;
        $this->assertFileExists($template, 'A component this contract answers for has moved or is gone, and the guard would pass by reading nothing.');

        // "media.x" and "slide.image.x", the two names a media reaches these templates under
        preg_match_all('/(?:\bmedia|\bslide\.image)\.([a-zA-Z]+)/', (string) file_get_contents($template), $matches);

        return array_values(array_unique($matches[1]));
    }
}
