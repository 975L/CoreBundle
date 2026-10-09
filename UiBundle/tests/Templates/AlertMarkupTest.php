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

// The type is named by a word above the text, read from the "ui" domain, and the text always lands in "alert__body"
class AlertMarkupTest extends TestCase
{
    public function testTheLabelNamesTheType(): void
    {
        $html = $this->render(['type' => 'warning', 'message' => 'Careful']);

        $this->assertStringContainsString('class="alert alert-warning"', $html);
        $this->assertStringContainsString('<span class="alert__label">ui:label.alert_warning</span>', $html);
    }

    // The label="false" ConfigBundle writes keeps the bare box, its own title already naming the list
    public function testNoLabelMeansNoWord(): void
    {
        $html = $this->render(['type' => 'info', 'message' => 'Plain', 'label' => 'false']);

        $this->assertStringContainsString('class="alert alert-info"', $html);
        $this->assertStringNotContainsString('alert__label', $html);
    }

    public function testTheContentIsWrappedInTheBody(): void
    {
        $html = $this->render(['content' => "First\nSecond"]);

        $this->assertStringContainsString('<div class="alert__body">First<br />' . "\n" . 'Second</div>', $html);
        $this->assertStringNotContainsString('text-center', $html);
    }

    // The bare environment the component renderer would otherwise bring, with the "to_bool" filter the label prop is read through and a "trans" echoing its domain and key
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addFilter(new TwigFilter('to_bool', static fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN)));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key, array $parameters = [], ?string $domain = null): string => $domain . ':' . $key));

        return $twig->render('components/Alert/Alert.html.twig', $context);
    }
}
