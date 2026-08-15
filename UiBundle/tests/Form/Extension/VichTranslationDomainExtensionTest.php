<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Extension;

use c975L\UiBundle\Form\Extension\VichTranslationDomainExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Vich\UploaderBundle\Form\Type\VichImageType;

// The upload fields of the admin used to render "vich_uploader.form_label.delete_confirm" as a raw key: VichUploaderBundle leaves both label domains at null, meaning "whatever domain the surrounding form uses", and inside EasyAdmin that is the dashboard's own domain rather than the one the bundle's translations sit in.
class VichTranslationDomainExtensionTest extends TestCase
{
    public function testBothVichTypesAreCovered(): void
    {
        $types = [...VichTranslationDomainExtension::getExtendedTypes()];

        // VichImageType only extends VichFileType in PHP, never through getParent() - an extension of the file type alone would leave every image field untranslated
        $this->assertContains(VichFileType::class, $types);
        $this->assertContains(VichImageType::class, $types);
    }

    public function testTheLabelsResolveToTheDomainVichShipsThemIn(): void
    {
        $options = $this->resolve();

        $this->assertSame('messages', $options['delete_label_translation_domain']);
        $this->assertSame('messages', $options['download_label_translation_domain']);
    }

    public function testAFieldNamingItsOwnDomainStillWins(): void
    {
        $options = $this->resolve(['delete_label_translation_domain' => 'ui']);

        $this->assertSame('ui', $options['delete_label_translation_domain']);
    }

    // Vich's own defaults first, then the extension on top, as the form registry chains them - the point being that this overrides a null the bundle sets, not an option it never declared
    private function resolve(array $options = []): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['label' => null, 'required' => false, 'attr' => []]);

        $type = new \ReflectionClass(VichFileType::class)->newInstanceWithoutConstructor();
        $type->configureOptions($resolver);
        new VichTranslationDomainExtension()->configureOptions($resolver);

        return $resolver->resolve($options);
    }
}
