<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Form\FontChoiceType;
use c975L\UiBundle\Registry\FontRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FontChoiceTypeTest extends TestCase
{
    private function createRegistry(array $fonts): FontRegistry
    {
        $registry = $this->createStub(FontRegistry::class);
        $registry->method('getFonts')->willReturn($fonts);

        return $registry;
    }

    public function testGetParentIsChoiceType(): void
    {
        $type = new FontChoiceType($this->createRegistry([]));

        $this->assertSame(ChoiceType::class, $type->getParent());
    }

    public function testGetBlockPrefix(): void
    {
        $type = new FontChoiceType($this->createRegistry([]));

        $this->assertSame('font_choice', $type->getBlockPrefix());
    }

    // Choices are built as label => value from the registry, lazily so the registry is only queried when resolved
    public function testConfigureOptionsBuildsChoicesFromRegistry(): void
    {
        $type = new FontChoiceType($this->createRegistry(['Roboto', 'Lato']));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertSame(['Roboto' => 'Roboto', 'Lato' => 'Lato'], $options['choices']);
        $this->assertTrue($options['placeholder']);
    }

    public function testConfigureOptionsChoicesAreEmptyWhenRegistryHasNoFonts(): void
    {
        $type = new FontChoiceType($this->createRegistry([]));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertSame([], $options['choices']);
    }

    // A caller must still be able to override 'choices', e.g. to merge in a stale value
    public function testChoicesOptionCanBeOverridden(): void
    {
        $type = new FontChoiceType($this->createRegistry(['Roboto']));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve(['choices' => ['Custom' => 'Custom']]);

        $this->assertSame(['Custom' => 'Custom'], $options['choices']);
    }
}
