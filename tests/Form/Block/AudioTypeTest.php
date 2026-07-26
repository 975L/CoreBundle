<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\AudioType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AudioTypeTest extends TestCase
{
    // The block is entirely defined by its uploaded media, format included - asking for the format again in this form would just be a second, contradictory source of truth
    public function testBuildFormAddsNoFieldAtAll(): void
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        (new AudioType())->buildForm($builder, []);

        $this->assertSame([], $added);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new AudioType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
