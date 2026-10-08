<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

// The icon sits beside the text only through "alert--icon", so the class follows the icon and the text always lands in "alert__body"
class AlertMarkupTest extends TestCase
{
    public function testTheIconLayoutClassComesWithTheIcon(): void
    {
        $html = $this->render(['type' => 'warning', 'message' => 'Careful']);

        $this->assertStringContainsString('class="alert alert-warning alert--icon"', $html);
        $this->assertStringContainsString('alert-warning.svg', $html);
    }

    // The icon="false" ConfigBundle writes keeps the plain box, nothing turning its inline tags into flex columns
    public function testNoIconMeansNoIconLayoutClass(): void
    {
        $html = $this->render(['type' => 'info', 'message' => 'Plain', 'icon' => 'false']);

        $this->assertStringContainsString('class="alert alert-info"', $html);
        $this->assertStringNotContainsString('alert--icon', $html);
        $this->assertStringNotContainsString('.svg', $html);
    }

    public function testTheContentIsWrappedInTheBody(): void
    {
        $html = $this->render(['content' => "First\nSecond"]);

        $this->assertStringContainsString('<div class="alert__body">First<br />' . "\n" . 'Second</div>', $html);
        $this->assertStringNotContainsString('text-center', $html);
    }

    // The bare environment the component renderer would otherwise bring, with the "to_bool" filter the icon prop is read through
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addFilter(new TwigFilter('to_bool', static fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN)));

        return $twig->render('components/Alert/Alert.html.twig', $context);
    }
}
