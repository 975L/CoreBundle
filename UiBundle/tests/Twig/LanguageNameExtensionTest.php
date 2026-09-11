<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Twig\LanguageNameExtension;
use PHPUnit\Framework\TestCase;

class LanguageNameExtensionTest extends TestCase
{
    public function testALanguageIsNamedAsALabelNamesItAndNotAsASentenceDoes(): void
    {
        $extension = new LanguageNameExtension();

        $this->assertSame('Français', $extension->languageName('fr'));
        $this->assertSame('Español', $extension->languageName('es'));
        $this->assertSame('English', $extension->languageName('en'));
    }
}
