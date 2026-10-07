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
use Twig\TwigFunction;

// The site search is a section only when it carries a heading, the W3C flagging an untitled one on every page holding the navbar's dialog
class AiSearchWrapperTest extends TestCase
{
    public function testAnUntitledSearchIsADiv(): void
    {
        $html = $this->render(['fieldId' => 'ai-search-dialog', 'title' => null]);

        $this->assertStringContainsString('<div class="ai-search"', $html);
        $this->assertStringNotContainsString('<section', $html);
    }

    public function testATitledSearchIsASection(): void
    {
        $html = $this->render(['fieldId' => 'ai-search-1', 'title' => 'Ask the site']);

        $this->assertStringContainsString('<section class="ai-search"', $html);
        $this->assertStringContainsString('</section>', $html);
    }

    // Renders the partial with its filters and functions stubbed
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates/ai_search'));
        $twig->addFilter(new TwigFilter('trans', static fn (string $id): string => $id));
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => '/' . $route));
        $twig->addFunction(new TwigFunction('ai_search_label', static fn (): ?string => null));
        $twig->addGlobal('app', ['request' => ['locale' => 'en']]);

        return $twig->render('_search.html.twig', $context);
    }
}
