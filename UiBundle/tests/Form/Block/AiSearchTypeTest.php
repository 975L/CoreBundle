<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\AiSearchType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AiSearchTypeTest extends TestCase
{
    // The search itself is the site's config: the block only says what its field is introduced with, all of it optional
    public function testBuildFormAddsOptionalIntroductionFields(): void
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new AiSearchType()->buildForm($builder, []);

        $this->assertSame(['title', 'placeholder', 'suggestions'], array_keys($added));
        foreach ($added as $name => $options) {
            $this->assertFalse($options['required'], "\"$name\" should be optional");
        }
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $resolver = new OptionsResolver();
        new AiSearchType()->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
