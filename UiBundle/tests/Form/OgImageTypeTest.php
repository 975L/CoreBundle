<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Form\OgImageType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File as FileConstraint;
use Vich\UploaderBundle\Form\Type\VichImageType;

// The share image an entity owns alone - SiteBundle's Page and ConfigBundle's UrlMetadata both draw this form, so what it carries is what either of them offers
class OgImageTypeTest extends TestCase
{
    // Records the fields buildForm() adds, keeping each one's type and options - a stubbed builder is enough, the type branching on nothing
    private function buildFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        new OgImageType()->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsTheUploadWidget(): void
    {
        $file = $this->buildFields()['file'];

        $this->assertSame(VichImageType::class, $file['type']);
        $this->assertFalse($file['options']['label']);
    }

    // Same 2M image-only ceiling as every other site image, VichImageOptions being what states it once
    public function testTheUploadIsConstrainedToAnImage(): void
    {
        $constraints = $this->buildFields()['file']['options']['constraints'];

        $this->assertInstanceOf(FileConstraint::class, $constraints[0]);
        $this->assertSame(2000000, $constraints[0]->maxSize);
    }

    // What the image shows, written as "og:image:alt" by both layouts - a share read out rather than displayed has nothing else to say
    public function testBuildFormAddsTheAlternativeText(): void
    {
        $alt = $this->buildFields()['alt'];

        $this->assertSame(TextType::class, $alt['type']);
        $this->assertSame('label.alt_text', $alt['options']['label']);
    }

    // An image is chosen before anyone thinks of describing it, and a share without the text is worth more than no share image at all
    public function testTheAlternativeTextIsOptional(): void
    {
        $this->assertFalse($this->buildFields()['alt']['options']['required']);
    }

    public function testConfigureOptionsMapsTheFormOntoAMedia(): void
    {
        $resolver = new OptionsResolver();
        new OgImageType()->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertSame(Media::class, $options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
