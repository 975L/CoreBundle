<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Form\ImageClassChoiceType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImageClassChoiceTypeTest extends TestCase
{
    public function testGetParentIsChoiceType(): void
    {
        $type = new ImageClassChoiceType();

        $this->assertSame(ChoiceType::class, $type->getParent());
    }

    public function testConfigureOptionsDefaultsToMultipleChoiceFromChoicesConst(): void
    {
        $type = new ImageClassChoiceType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertSame(ImageClassChoiceType::CHOICES, $options['choices']);
        $this->assertTrue($options['multiple']);
        $this->assertFalse($options['required']);
    }
}
