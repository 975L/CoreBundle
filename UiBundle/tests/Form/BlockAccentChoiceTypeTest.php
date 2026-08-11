<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Form\BlockAccentChoiceType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlockAccentChoiceTypeTest extends TestCase
{
    private function options(): array
    {
        $resolver = new OptionsResolver();
        new BlockAccentChoiceType()->configureOptions($resolver);

        return $resolver->resolve();
    }

    public function testItIsAChoiceType(): void
    {
        $this->assertSame(ChoiceType::class, new BlockAccentChoiceType()->getParent());
    }

    // Not optional and not placeholdered would make "no accent" unreachable, which is what every card stored before this field existed holds
    public function testAnAccentIsOptionalAndHasANoneplaceholder(): void
    {
        $options = $this->options();

        $this->assertFalse($options['required']);
        $this->assertSame('label.accent_none', $options['placeholder']);
        $this->assertSame('ui', $options['translation_domain']);
    }

    public function testItOffersTwelveHues(): void
    {
        $this->assertCount(12, BlockAccentChoiceType::CHOICES);
        $this->assertSame(BlockAccentChoiceType::CHOICES, $this->options()['choices']);
    }

    // Each stored value lands in a "card--accent-<hue>" class and reads a "--block-accent-<hue>" token, so nothing a class name or a custom property can't hold may be offered here
    public function testEveryStoredValueIsClassNameSafe(): void
    {
        foreach (BlockAccentChoiceType::CHOICES as $label => $hue) {
            $this->assertMatchesRegularExpression('/^[a-z]+$/', $hue);
            $this->assertSame('label.accent_' . $hue, $label);
        }
    }
}
