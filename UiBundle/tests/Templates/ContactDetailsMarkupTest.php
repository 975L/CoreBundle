<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Service\ContactSnippetBuilder;
use c975L\UiBundle\Twig\ContactExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

class ContactDetailsMarkupTest extends TestCase
{
    // Every prop being optional, a block filled in with next to nothing must still render, and render nothing empty
    public function testAnAlmostEmptyBlockRendersNoEmptyRowAndNoGraph(): void
    {
        $html = $this->render(['telephone' => '04 50 00 00 00']);

        $this->assertStringContainsString('04 50 00 00 00', $html);
        $this->assertStringNotContainsString('application/ld+json', $html);
        $this->assertStringNotContainsString('label.address', $html);
        $this->assertStringNotContainsString('label.email', $html);
        $this->assertStringNotContainsString('contact-details__hours', $html);
    }

    // A dialer needs the bare number, the visitor reads the spaced one
    public function testPhoneIsClickable(): void
    {
        $html = $this->render(['telephone' => '04 50 00 00 00']);

        $this->assertStringContainsString('<a href="tel:0450000000">04 50 00 00 00</a>', $html);
    }

    // Displayed, never linked: a mailto is the first thing an address harvester follows
    public function testEmailIsRenderedAsPlainText(): void
    {
        $html = $this->render(['email' => 'contact@example.com']);

        $this->assertStringContainsString('contact@example.com', $html);
        $this->assertStringNotContainsString('mailto:', $html);
    }

    // The grid in sass/_contact-details.scss lays out one cell per pair: bare dt/dd would be placed as unrelated cells
    public function testEachPairIsWrappedForTheGrid(): void
    {
        $html = $this->render([
            'telephone' => '04 50 00 00 00',
            'addressLocality' => 'Annecy',
            'hours' => [['days' => ['Monday'], 'opens' => '9:00', 'closes' => '12:00']],
        ]);

        $this->assertSame(3, substr_count($html, '<dt>'));
        $this->assertSame(3, substr_count($html, 'class="contact-details__item'));
    }

    public function testFilledInFieldsArePublishedAsAJsonLdGraph(): void
    {
        $html = $this->render([
            'schemaType' => 'AutoRepair',
            'name' => 'Autotech',
            'addressPostalCode' => '74930',
            'addressLocality' => 'Scientrier',
        ]);

        $this->assertStringContainsString('<script type="application/ld+json">', $html);
        $this->assertSame('AutoRepair', $this->graph($html)['@type']);
        $this->assertSame('Scientrier', $this->graph($html)['address']['addressLocality']);
    }

    // Consecutive days collapse, so the two ranges of a business closing for lunch read as two lines, not ten
    public function testConsecutiveOpeningDaysRenderAsARange(): void
    {
        $html = $this->render([
            'hours' => [
                ['days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'opens' => '9:00', 'closes' => '12:00'],
                ['days' => ['Saturday'], 'opens' => '9:00', 'closes' => '12:00'],
            ],
        ]);

        $this->assertStringContainsString('<dt>label.monday - label.friday</dt>', $html);
        $this->assertStringContainsString('<dt>label.saturday</dt>', $html);
        $this->assertStringContainsString('<dd>9:00 - 12:00</dd>', $html);
    }

    // Both times being optional, a row saved with days only must take the whole section with it, heading included
    public function testARowWithoutTimesRendersNoHoursSectionAtAll(): void
    {
        $html = $this->render(['hours' => [['days' => ['Monday'], 'opens' => '', 'closes' => '']]]);

        $this->assertStringNotContainsString('contact-details__hours', $html);
        $this->assertStringNotContainsString('label.opening_hours', $html);
    }

    private function graph(string $html): array
    {
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        return json_decode($matches[1], true);
    }

    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        // Untranslated keys come back as-is, which is what the assertions above read
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        // What TwigBundle assembles from the #[AsTwigFunction] attributes: the extension reads them, the runtime loader hands over the instance the callables are called on
        $twig->addExtension(new AttributeExtension(ContactExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            ContactExtension::class => static fn (): ContactExtension => new ContactExtension(new ContactSnippetBuilder()),
        ]));

        return $twig->render('components/Contact/Details.html.twig', $context);
    }
}
