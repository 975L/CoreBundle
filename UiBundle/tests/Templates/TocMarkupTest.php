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
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\Twig\PropsTokenParser;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigFilter;

// The entries are the caller's - what actually rendered on that page - so the component never offers an anchor for a section the page left out
class TocMarkupTest extends TestCase
{
    public function testEachEntryIsALinkToItsOwnAnchor(): void
    {
        $html = $this->render([['anchor' => 'resume', 'label' => 'Résumé'], ['anchor' => 'informations', 'label' => 'Informations']]);

        $this->assertStringContainsString('href="#resume"', $html);
        $this->assertStringContainsString('>Résumé</a>', $html);
        $this->assertStringContainsString('href="#informations"', $html);
    }

    // Navigation, and said as such: a page holding two <nav> needs the second one named, or a screen reader announces the same thing twice
    public function testTheSummaryIsANavigationCarryingItsOwnName(): void
    {
        $html = $this->render([['anchor' => 'resume', 'label' => 'Résumé']]);

        $this->assertStringContainsString('<nav class="toc ', $html);
        $this->assertStringContainsString('aria-label="label.toc"', $html);
    }

    // A page whose summary reads better as something else than "Contents" says so itself
    public function testACallerCanNameTheSummaryItself(): void
    {
        $html = $this->render([['anchor' => 'resume', 'label' => 'Résumé']], ['label' => 'Dans ce livre']);

        $this->assertStringContainsString('aria-label="Dans ce livre"', $html);
    }

    // Nothing at all rather than an empty bar: the bar is sticky and opaque, and would sit across the page holding no link
    public function testAPageWithNoSectionToPointAtRendersNoBar(): void
    {
        $this->assertSame('', trim($this->render([])));
    }

    // An empty id="" is invalid HTML, the same reason every other component here writes it only when set
    public function testTheIdIsOnlyWrittenWhenGiven(): void
    {
        $this->assertStringNotContainsString('id=""', $this->render([['anchor' => 'resume', 'label' => 'Résumé']]));
        $this->assertStringContainsString('id="book-toc"', $this->render([['anchor' => 'resume', 'label' => 'Résumé']], ['id' => 'book-toc']));
    }

    /**
     * @param array<int, array<string, string>> $entries
     * @param array<string, string>             $context
     */
    private function render(array $entries, array $context = []): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        $twig->addTokenParser(new PropsTokenParser());
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        // What TwigEnvironmentConfigurator does in the application: "props" reads the attributes bag off the context, and none exists outside a rendered component
        $twig->getRuntime(EscaperRuntime::class)->addSafeClass(ComponentAttributes::class, ['html']);

        return $twig->render('components/Text/Toc.html.twig', $context + [
            'entries' => $entries,
            'attributes' => new ComponentAttributes([], $twig->getRuntime(EscaperRuntime::class)),
        ]);
    }
}
