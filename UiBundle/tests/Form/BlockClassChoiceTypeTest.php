<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Form\BlockClassChoiceType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlockClassChoiceTypeTest extends TestCase
{
    public function testGetParentIsChoiceType(): void
    {
        $type = new BlockClassChoiceType();

        $this->assertSame(ChoiceType::class, $type->getParent());
    }

    public function testConfigureOptionsDefaultsToMultipleChoiceFromChoicesConst(): void
    {
        $type = new BlockClassChoiceType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertSame(BlockClassChoiceType::CHOICES, $options['choices']);
        $this->assertTrue($options['multiple']);
        $this->assertFalse($options['required']);
    }

    // Widths and nothing else: the "box-shadow" this list used to offer changed no pixel, the Card component writing that class on every card it renders and BlockShadowChoiceType being where a shadow has been chosen since
    public function testItHoldsWidthsAndNothingElse(): void
    {
        foreach (BlockClassChoiceType::CHOICES as $label => $class) {
            $this->assertStringStartsWith('width-', $class);
            $this->assertStringStartsWith('label.css_class_width_', $label);
        }
    }

    // Its own labels, not the "label.css_classes" pair ImageClassChoiceType and the media screen share: those two do hold classes of several kinds, where this one holds a width and should say so
    public function testItSaysItHoldsAWidth(): void
    {
        $type = new BlockClassChoiceType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertSame('label.block_width', $options['label']);
        $this->assertSame('label.block_width_help', $options['help']);
    }
}
