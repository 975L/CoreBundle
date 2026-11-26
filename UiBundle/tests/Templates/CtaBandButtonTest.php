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

// A band shows its button only with both a label and a target, a band without them being a statement on its own
class CtaBandButtonTest extends TestCase
{
    // Checks a label and a target give the button
    public function testLabelAndTargetGiveTheButton(): void
    {
        $html = $this->render(['title' => 'Title', 'text' => '', 'ctaLabel' => 'Go', 'ctaUrl' => '/target']);

        $this->assertStringContainsString('<a class="section-btn" href="/target">Go</a>', $html);
    }

    // Checks a band missing its label, its target or both renders no button
    public function testNoButtonWithoutLabelOrTarget(): void
    {
        foreach ([['ctaLabel' => '', 'ctaUrl' => '/target'], ['ctaLabel' => 'Go', 'ctaUrl' => ''], []] as $button) {
            $html = $this->render(['title' => 'Title', 'text' => ''] + $button);

            $this->assertStringContainsString('cta-band__title', $html);
            $this->assertStringNotContainsString('section-btn', $html);
        }
    }

    // The bare environment the component renderer would otherwise bring
    private function render(array $context): string
    {
        $twig = HtmlSanitizerKernel::register(new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates')));

        return $twig->render('components/Cta/Band.html.twig', $context);
    }
}
