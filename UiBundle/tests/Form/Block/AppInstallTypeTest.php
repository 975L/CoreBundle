<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\AppInstallType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;

class AppInstallTypeTest extends TestCase
{
    // Nothing is required: the block stands on the app's own config, and the Play Store link waits for the listing to be public
    public function testBuildFormAddsOptionalFields(): void
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new AppInstallType()->buildForm($builder, []);

        $this->assertSame(['title', 'text', 'playStore'], array_keys($added));
        foreach ($added as $field => $options) {
            $this->assertFalse($options['required'], "\"$field\" should be optional");
        }
    }
}
