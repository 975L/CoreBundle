<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\HeroType;
use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

class HeroTypeTest extends TestCase
{
    private array $addedTypes = [];

    private function buildAddedFields(): array
    {
        $added = [];
        $this->addedTypes = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;
            $this->addedTypes[$name] = $type;

            return $builder;
        });

        (new HeroType(new BlockAnchorSlugger(new AsciiSlugger())))->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        foreach (['badge', 'title', 'titleLevel', 'subtitle', 'hasBackgroundImage', 'background', 'primaryLabel', 'primaryUrl', 'secondaryLabel', 'secondaryUrl', 'statValue', 'statLabel', 'anchor'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the Hero form");
        }
    }

    public function testOnlyPrimaryCtaAndTitleAreRequired(): void
    {
        $added = $this->buildAddedFields();

        $this->assertFalse($added['badge']['required']);
        $this->assertFalse($added['subtitle']['required']);
        $this->assertFalse($added['hasBackgroundImage']['required']);
        $this->assertFalse($added['secondaryLabel']['required']);
        $this->assertFalse($added['secondaryUrl']['required']);
        $this->assertFalse($added['statValue']['required']);
        $this->assertFalse($added['statLabel']['required']);
    }

    // "title" and "subtitle" both go through Trix (not a plain textarea/text input) so an editor can
    // emphasize a word - "hasBackgroundImage" is a plain checkbox toggling the full-width background layout
    public function testTitleAndSubtitleUseTrixEditorAndBackgroundIsACheckbox(): void
    {
        $this->buildAddedFields();

        $this->assertSame(TrixEditorType::class, $this->addedTypes['title']);
        $this->assertSame(TrixEditorType::class, $this->addedTypes['subtitle']);
        $this->assertSame(CheckboxType::class, $this->addedTypes['hasBackgroundImage']);
    }

    // "titleLevel" only ever offers h1/h2, with no empty choice: a hero always has a heading, the field
    // only picks which level it takes so a page template printing its own <h1> doesn't end up with two
    public function testTitleLevelIsAnH1OrH2ChoiceWithNoPlaceholder(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(ChoiceType::class, $this->addedTypes['titleLevel']);
        $this->assertSame(['h1' => 'h1', 'h2' => 'h2'], $added['titleLevel']['choices']);
        $this->assertFalse($added['titleLevel']['placeholder']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new HeroType(new BlockAnchorSlugger(new AsciiSlugger()));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
