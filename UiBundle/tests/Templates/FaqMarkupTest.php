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

// The questions block draws an accordion and, on one column only, the FAQPage payload a search engine reads off it
class FaqMarkupTest extends TestCase
{
    private const array ITEMS = [
        ['question' => 'Livrez-vous à l\'étranger ?', 'answer' => '<em>Oui</em>, partout en Europe.'],
        ['question' => 'Sous quel délai ?', 'answer' => 'Trois jours ouvrés.'],
    ];

    // "details"/"summary" and not a script: the accordion works before any JavaScript loads and a printed page shows every answer
    public function testEachQuestionIsADetailsOpenedByItsOwnSummary(): void
    {
        $html = $this->render(['items' => self::ITEMS]);

        // Escaped on the page, where the payload below carries it as its own character: what an editor typed is data on both sides
        $this->assertStringContainsString('<summary class="faq__question">Livrez-vous à l&#039;étranger ?</summary>', $html);
        $this->assertSame(2, substr_count($html, '<details class="faq__item"'));
        $this->assertStringContainsString('<em>Oui</em>', $html);
    }

    // The first answer already unfolded, for a page whose first question is the one everybody asks - and it alone
    public function testOnlyTheFirstAnswerIsUnfoldedAndOnlyWhenAskedFor(): void
    {
        $this->assertStringNotContainsString(' open>', $this->render(['items' => self::ITEMS]));
        $this->assertSame(1, substr_count($this->render(['items' => self::ITEMS, 'openFirst' => true]), ' open>'));
    }

    // A row with no question is an entry an editor started and left behind, and it must reach neither the page nor the payload
    public function testAQuestionlessRowIsDroppedFromThePageAndFromThePayload(): void
    {
        $html = $this->render(['items' => [...self::ITEMS, ['question' => '', 'answer' => 'Une réponse orpheline']]]);

        $this->assertStringNotContainsString('orpheline', $html);
        $this->assertSame(2, substr_count($html, '<details class="faq__item"'));
    }

    // Nothing at all rather than an empty section, a block whose questions were all left blank having nothing to say
    public function testABlockWithNoUsableQuestionRendersNothing(): void
    {
        $this->assertSame('', trim($this->render(['items' => [['question' => '', 'answer' => '']]])));
        $this->assertSame('', trim($this->render(['title' => 'Questions fréquentes'])));
    }

    // The payload schema.org reads, built from the very questions above it and stripped of their markup
    public function testTheOneColumnLayoutPublishesTheFaqPagePayload(): void
    {
        $html = $this->render(['items' => self::ITEMS]);
        $payload = $this->payloadOf($html);

        $this->assertSame('FAQPage', $payload['@type']);
        $this->assertCount(2, $payload['mainEntity']);
        $this->assertSame('Livrez-vous à l\'étranger ?', $payload['mainEntity'][0]['name']);
        $this->assertSame('Oui, partout en Europe.', $payload['mainEntity'][0]['acceptedAnswer']['text']);
    }

    // Google reads a FAQPage as one ordered list, and a two-column layout says the page is not one
    public function testTheTwoColumnLayoutPublishesNoPayloadAtAll(): void
    {
        $html = $this->render(['items' => self::ITEMS, 'columns' => 2]);

        $this->assertStringContainsString('faq--cols2', $html);
        $this->assertStringNotContainsString('application/ld+json', $html);
    }

    private function payloadOf(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $matches), 'No FAQPage payload was published.');

        return json_decode($matches[1], true, 512, \JSON_THROW_ON_ERROR);
    }

    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));

        return $twig->render('blocks/Faq.html.twig', $context);
    }
}
