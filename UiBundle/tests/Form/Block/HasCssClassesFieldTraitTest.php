<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class HasCssClassesFieldTraitTest extends TestCase
{
    /**
     * @return array<string, array{type: ?string, options: array}>
     */
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = ['type' => $type, 'options' => $options];

            return $builder;
        });

        new HasCssClassesFieldTraitStub()->buildForm($builder, []);

        return $added;
    }

    // The whole point is the classes the bundle cannot know, so no closed list can stand in for it - contrast BlockClassChoiceType, which is the right field for the styles the bundle does ship
    public function testTheFieldIsFreeTextAndOptional(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame(['cssClasses'], array_keys($added));
        $this->assertSame(TextType::class, $added['cssClasses']['type']);
        $this->assertFalse($added['cssClasses']['options']['required']);
    }

    // A field whose value only does something if the site's own stylesheet defines it needs its help text saying so
    public function testTheFieldIsLabelledAndExplained(): void
    {
        $added = $this->buildAddedFields();

        $this->assertSame('label.css_classes_free', $added['cssClasses']['options']['label']);
        $this->assertSame('label.css_classes_free_help', $added['cssClasses']['options']['help']);
    }
}
