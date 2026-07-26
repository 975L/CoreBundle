<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The aiAssistant/aiRephrase controllers read their own data-* attributes (plain dataset/querySelector,
// no Stimulus targets/values sugar - see the note atop each JS file), so nothing at runtime complains
// when a template writes a name the controller never looks for: values read back empty and targets are
// null, which for Donovan's question box means ask() returns before fetch() and the box just looks dead.
// This locks the two sides together, the JS file being the reference.
class AiControllerDataAttributesTest extends TestCase
{
    // [JS controller, template rendering it]
    public static function controllerProvider(): array
    {
        return [
            'aiAssistant' => ['assets/js/ai-assistant.js', 'templates/management/_ai_assistant_widget.html.twig'],
            'aiRephrase' => ['assets/js/ai-rephrase.js', 'templates/form/_ai_rephrase.html.twig'],
        ];
    }

    #[DataProvider('controllerProvider')]
    public function testTemplateWritesEveryAttributeTheControllerReads(string $jsPath, string $twigPath): void
    {
        $js = $this->read($jsPath);
        $twig = $this->read($twigPath);

        $expected = array_merge($this->datasetAttributes($js), $this->targetAttributes($js));
        $this->assertNotEmpty($expected, sprintf('No data-* attribute found in "%s", the test itself is broken.', $jsPath));

        foreach ($expected as $attribute) {
            $this->assertStringContainsString($attribute, $twig, sprintf('"%s" reads "%s" but "%s" never writes it.', $jsPath, $attribute, $twigPath));
        }
    }

    // "this.element.dataset.aiAssistantAskUrlValue" -> "data-ai-assistant-ask-url-value"
    private function datasetAttributes(string $js): array
    {
        preg_match_all('/dataset\.([A-Za-z0-9]+)/', $js, $matches);

        return array_map(
            static fn (string $property): string => 'data-' . strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $property)),
            array_unique($matches[1]),
        );
    }

    // "querySelector('[data-ai-assistant-target=\"log\"]')" -> "data-ai-assistant-target=\"log\""
    private function targetAttributes(string $js): array
    {
        preg_match_all('/\[(data-[a-z0-9-]+-target="[a-z0-9-]+")\]/', $js, $matches);

        return array_unique($matches[1]);
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
