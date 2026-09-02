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

// What a source hands back in CollectionItem::$data reaches this adapter as plain block data, so the props opening the card's stat variant are read here exactly as an editor's own would be (see CardStatVariantTest for the variant itself)
class CollectionItemStatVariantTest extends TestCase
{
    // A portrait, a qualifying line and a score: an item drawn from a source reads like the card an app renders itself when it holds the entity
    public function testAnItemCarryingTheStatPropsHandsThemToTheCard(): void
    {
        $html = $this->render(['title' => 'Item Un', 'eyebrow' => 'Seigneur', 'imageUrl' => '/uploads/photo.jpg']);

        $this->assertStringContainsString('eyebrow="Seigneur"', $html);
        $this->assertStringContainsString('src="/uploads/photo.jpg"', $html);
        $this->assertStringContainsString('alt="Item Un"', $html);
        $this->assertStringNotContainsString('card-data', $html);
    }

    // A rating of 0 is a real score, "not rated" being the key left out - same reading as the card's own
    public function testAZeroRatingOpensTheVariantWhereAnAbsentOneDoesNot(): void
    {
        $this->assertStringContainsString('rating="0"', $this->render(['title' => 'Item Un', 'rating' => 0]));
        $this->assertStringNotContainsString('rating=', $this->render(['title' => 'Item Un', 'content' => 'Text']));
    }

    // The detail url first, the source's own url behind it: an item linked to its detail page must not be sent to the outside link instead
    public function testTheStatCardIsLinkedToTheDetailPageWhenThereIsOne(): void
    {
        $html = $this->render(['title' => 'Item Un', 'eyebrow' => 'Seigneur', 'detailUrl' => '/pages/collection/item', 'url' => 'https://example.org']);

        $this->assertStringContainsString('titleUrl="/pages/collection/item"', $html);
    }

    // The item's own content is nested bare, the stat card already carrying its alignment - unlike the teaser, which wraps it in a centered ".card-data"
    public function testTheContentIsNestedWithoutAWrapperOfItsOwn(): void
    {
        $html = $this->render(['title' => 'Item Un', 'eyebrow' => 'Seigneur', 'content' => "Une ligne\nUne autre"]);

        $this->assertStringContainsString("Une ligne<br />\nUne autre", $html);
        $this->assertStringNotContainsString('text-center', $html);
    }

    // "class" is a list on a stored block and a plain string in a source's own data, and both have to reach the card as one attribute
    public function testTheClassIsTakenAsAListOrAsAString(): void
    {
        $this->assertStringContainsString('class="variant-custom"', $this->render(['title' => 'Item Un', 'eyebrow' => 'Seigneur', 'class' => 'variant-custom']));
        $this->assertStringContainsString('class="card--accent-primary shadow"', $this->render(['title' => 'Item Un', 'class' => ['card--accent-primary', 'shadow']]));
    }

    // The "compact" variant is a class on the card and not a markup of its own, so it has to reach it beside whatever class the item already carried
    public function testTheCompactVariantAddsItsClassToTheOneTheItemAlreadyCarries(): void
    {
        $this->assertStringContainsString('class="card--compact"', $this->render(['title' => 'Item Un', 'content' => 'Text', 'variant' => 'compact']));
        $this->assertStringContainsString('class="variant-custom card--compact"', $this->render(['title' => 'Item Un', 'content' => 'Text', 'class' => 'variant-custom', 'variant' => 'compact']));
        $this->assertStringNotContainsString('card--compact', $this->render(['title' => 'Item Un', 'content' => 'Text']));
    }

    // An item carrying none of them is the plain teaser this kind has always rendered
    public function testAnItemCarryingNoneOfThemKeepsThePlainCard(): void
    {
        $html = $this->render(['title' => 'Item Un', 'content' => 'Text']);

        $this->assertStringNotContainsString('eyebrow=', $html);
        $this->assertStringContainsString('content="Text"', $html);
    }

    // A bare Environment writes the "<twig:...>" calls out as text, which is what these assertions read - and stringifies "stats" on the way, where the component syntax hands the array over as it is (see TwigPreLexer). That one warning is dropped here rather than worked around in the template
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        set_error_handler(static fn (int $severity, string $message): bool => str_contains($message, 'Array to string conversion'), \E_WARNING);

        try {
            return $twig->render('blocks/CollectionItem.html.twig', $context);
        } finally {
            restore_error_handler();
        }
    }
}
