<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\HasCssClassesFieldTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

// Minimal HasCssClassesFieldTrait consumer, standing in for a real text kind's FormType - its own file (not inlined in the test class) since src/Tests classes are autoloadable by consuming apps, whose attribute route loader recursively reflects every class under the bundle root
class HasCssClassesFieldTraitStub extends AbstractType
{
    use HasCssClassesFieldTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addCssClassesField($builder);
    }
}
