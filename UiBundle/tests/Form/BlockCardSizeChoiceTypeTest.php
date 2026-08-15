<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Form\BlockCardSizeChoiceType;
use c975L\UiBundle\Form\BlockClassChoiceType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

// What is stored is a count to the row and not a measurement: each step is a --card-width-* token read off the page measure, so a site framing its content tighter keeps its row whole
class BlockCardSizeChoiceTypeTest extends TestCase
{
    private function options(): array
    {
        $resolver = new OptionsResolver();
        new BlockCardSizeChoiceType()->configureOptions($resolver);

        return $resolver->resolve();
    }

    public function testGetParentIsChoiceType(): void
    {
        $this->assertSame(ChoiceType::class, new BlockCardSizeChoiceType()->getParent());
    }

    // The default row of three is the placeholder and not a choice: an unset value writes no class, and every card stored before the field existed goes on holding its row
    public function testTheDefaultIsAPlaceholderAndNotAChoice(): void
    {
        $options = $this->options();

        $this->assertFalse($options['required']);
        $this->assertSame('label.card_size_default', $options['placeholder']);
        $this->assertNotContains('default', BlockCardSizeChoiceType::CHOICES);
    }

    public function testItCarriesItsOwnLabelHelpAndDomain(): void
    {
        $options = $this->options();

        $this->assertSame('label.card_size', $options['label']);
        $this->assertSame('label.card_size_help', $options['help']);
        $this->assertSame('ui', $options['translation_domain']);
        $this->assertSame(BlockCardSizeChoiceType::CHOICES, $options['choices']);
    }

    // Each stored value lands in a "card--<step>" / "flip-card--<step>" class reading a --card-width-<step> token of that same name, so nothing a class name cannot hold may be offered here
    public function testEveryStoredValueIsClassNameSafe(): void
    {
        $this->assertSame(['compact', 'big'], array_values(BlockCardSizeChoiceType::CHOICES));

        foreach (BlockCardSizeChoiceType::CHOICES as $label => $step) {
            $this->assertMatchesRegularExpression('/^[a-z]+$/', $step);
            $this->assertSame('label.card_size_' . $step, $label);
        }
    }

    // Two fields, two questions: this one answers how many of a card go to the row, the width list beside it how wide one block is - the two held apart is what keeps either answer readable
    public function testItSharesNoValueWithTheBlockWidthList(): void
    {
        $this->assertSame([], array_intersect(
            array_values(BlockCardSizeChoiceType::CHOICES),
            array_values(BlockClassChoiceType::CHOICES)
        ));
    }
}
