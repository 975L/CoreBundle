<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Form\BlockRadiusChoiceType;
use c975L\UiBundle\Form\BlockShadowChoiceType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The two fields shaping a block's own surface, held to one scale on purpose: a ".cards" row mixes cards and flip cards, and one design decision has to read the same on both sides of it
class BlockSurfaceChoiceTypeTest extends TestCase
{
    private function options(AbstractType $type): array
    {
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        return $resolver->resolve();
    }

    public function testBothAreChoiceTypes(): void
    {
        $this->assertSame(ChoiceType::class, new BlockRadiusChoiceType()->getParent());
        $this->assertSame(ChoiceType::class, new BlockShadowChoiceType()->getParent());
    }

    // "Thème" is the placeholder and not a choice, so an unset value writes no class at all and every block stored before these fields existed goes on rendering as it did
    public function testThemeIsAPlaceholderAndNotAChoice(): void
    {
        $radius = $this->options(new BlockRadiusChoiceType());
        $shadow = $this->options(new BlockShadowChoiceType());

        $this->assertFalse($radius['required']);
        $this->assertFalse($shadow['required']);
        $this->assertSame('label.radius_theme', $radius['placeholder']);
        $this->assertSame('label.shadow_theme', $shadow['placeholder']);
        $this->assertNotContains('theme', BlockRadiusChoiceType::CHOICES);
        $this->assertNotContains('theme', BlockShadowChoiceType::CHOICES);
    }

    public function testBothCarryTheirOwnLabelHelpAndDomain(): void
    {
        $radius = $this->options(new BlockRadiusChoiceType());
        $shadow = $this->options(new BlockShadowChoiceType());

        $this->assertSame('label.radius', $radius['label']);
        $this->assertSame('label.radius_help', $radius['help']);
        $this->assertSame('ui', $radius['translation_domain']);
        $this->assertSame('label.shadow', $shadow['label']);
        $this->assertSame('label.shadow_help', $shadow['help']);
        $this->assertSame('ui', $shadow['translation_domain']);
    }

    // One scale for the two, which is what lets a row mixing both kinds read as one decision - the steps drifting apart is the failure this locks out
    public function testTheTwoOfferTheVerySameSteps(): void
    {
        $this->assertSame(['none', 'small', 'medium', 'large'], array_values(BlockRadiusChoiceType::CHOICES));
        $this->assertSame(
            array_values(BlockRadiusChoiceType::CHOICES),
            array_values(BlockShadowChoiceType::CHOICES)
        );
        $this->assertSame(BlockRadiusChoiceType::CHOICES, $this->options(new BlockRadiusChoiceType())['choices']);
        $this->assertSame(BlockShadowChoiceType::CHOICES, $this->options(new BlockShadowChoiceType())['choices']);
    }

    // Each stored value lands in a "block-radius-<step>" / "block-shadow-<step>" class reading a token of that same name, so nothing a class name cannot hold may be offered here
    public function testEveryStoredValueIsClassNameSafe(): void
    {
        foreach (['radius' => BlockRadiusChoiceType::CHOICES, 'shadow' => BlockShadowChoiceType::CHOICES] as $field => $choices) {
            foreach ($choices as $label => $step) {
                $this->assertMatchesRegularExpression('/^[a-z]+$/', $step);
                $this->assertSame('label.' . $field . '_' . $step, $label);
            }
        }
    }
}
