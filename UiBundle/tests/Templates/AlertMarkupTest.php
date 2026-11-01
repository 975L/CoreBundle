<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Tests\Fixtures\HtmlSanitizerKernel;
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

    // An editor's text: what it may hold stays, what would run goes, and a bare newline still breaks the line
    public function testTheContentIsSanitized(): void
    {
        $html = $this->render(['content' => "<strong>First</strong>\nSecond<script>alert(1)</script><img src=\"/a.png\" onerror=\"alert(2)\">"]);

        $this->assertStringContainsString('<strong>First</strong><br />', $html);
        $this->assertStringNotContainsString('alert(', $html);
    }

    // A block saved with no text at all renders an empty body rather than a TypeError from the filter
    public function testNoContentRendersAnEmptyBody(): void
    {
        $this->assertStringContainsString('<div class="alert__body"></div>', $this->render([]));
    }

    // The bare environment the component renderer would otherwise bring, with the "to_bool" filter the label prop is read through and a "trans" echoing its domain and key
    private function render(array $context): string
    {
        $twig = HtmlSanitizerKernel::register(new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates')));
        $twig->addFilter(new TwigFilter('to_bool', static fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN)));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key, array $parameters = [], ?string $domain = null): string => $domain . ':' . $key));

        return $twig->render('components/Alert/Alert.html.twig', $context);
    }
}
