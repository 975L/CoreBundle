<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Form\AnimationChoiceType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnimationChoiceTypeTest extends TestCase
{
    public function testGetParentIsChoiceType(): void
    {
        $type = new AnimationChoiceType();

        $this->assertSame(ChoiceType::class, $type->getParent());
    }

    public function testConfigureOptionsDefaultsToSingleChoiceFromChoicesConst(): void
    {
        $type = new AnimationChoiceType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertSame(AnimationChoiceType::CHOICES, $options['choices']);
        $this->assertFalse($options['multiple']);
        $this->assertFalse($options['required']);
    }
}
