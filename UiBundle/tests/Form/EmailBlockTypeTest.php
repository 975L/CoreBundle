<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Form\EmailBlockType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmailBlockTypeTest extends TestCase
{
    private function buildStaticFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });
        $builder->method('addEventListener')->willReturnSelf();

        new EmailBlockType()->buildForm($builder, []);

        return $added;
    }

    // Captures the PRE_SET_DATA listener and fires it with $block, returning every field added on the inner (event) form - mirrors what happens when a row of the "blocks" collection is rendered
    private function firePreSetData(?EmailBlock $block): array
    {
        $listener = null;
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $builder->method('addEventListener')->willReturnCallback(
            function (string $eventName, callable $callback) use (&$listener, $builder) {
                $listener = $callback;

                return $builder;
            }
        );

        new EmailBlockType()->buildForm($builder, []);

        $added = [];
        $innerForm = $this->createStub(FormInterface::class);
        $innerForm->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $innerForm) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $innerForm;
        });

        $listener(new PreSetDataEvent($innerForm, $block));

        return $added;
    }

    public function testTypeChoicesCoverEveryEmailBlockType(): void
    {
        $added = $this->buildStaticFields();

        $this->assertSame(EmailBlock::TYPES, array_values($added['type']['options']['choices']));
    }

    public function testLevelChoicesCoverBothHeadingLevels(): void
    {
        $added = $this->buildStaticFields();

        $this->assertSame([EmailBlock::LEVEL_H1, EmailBlock::LEVEL_H2], array_values($added['level']['options']['choices']));
    }

    public function testIdFieldCarriesTheBlockId(): void
    {
        $block = new EmailBlock();
        $reflection = new \ReflectionProperty(EmailBlock::class, 'id');
        $reflection->setValue($block, 42);

        $added = $this->firePreSetData($block);

        $this->assertSame(42, $added['id']['options']['data']);
    }

    public function testIdFieldIsNullWhenNoBlockYetExists(): void
    {
        $added = $this->firePreSetData(null);

        $this->assertNull($added['id']['options']['data']);
    }

    public function testConfigureOptionsSetsDataClassAndTranslationDomain(): void
    {
        $resolver = new OptionsResolver();
        new EmailBlockType()->configureOptions($resolver);

        $resolved = $resolver->resolve([]);

        $this->assertSame(EmailBlock::class, $resolved['data_class']);
        $this->assertFalse($resolved['label']);
        $this->assertSame('ui', $resolved['translation_domain']);
    }
}
