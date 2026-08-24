<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\ReviewTokenSigner;
use PHPUnit\Framework\TestCase;

// What stands between a visitor and an id of their choosing, the review url naming what it is about through this and through nothing else
class ReviewTokenSignerTest extends TestCase
{
    public function testWhatWasSignedIsWhatComesBack(): void
    {
        $signer = $this->signer();

        $this->assertSame(['ownerType' => 'book', 'ownerId' => 12], $signer->unsign($signer->sign('book', 12)));
    }

    // The whole point: the id is nowhere in the url, so /review/... says nothing about the next thing in the catalog
    public function testTheTokenPrintsNeitherTheTypeNorTheId(): void
    {
        $token = $this->signer()->sign('book', 12);

        $this->assertStringNotContainsString('book', $token);
        $this->assertStringNotContainsString('12', $token);
    }

    // Two things get two tokens, and the same thing gets the same one - a url that changed on every render would break every link ever shared
    public function testTheTokenIsStableAndTellsTwoThingsApart(): void
    {
        $signer = $this->signer();

        $this->assertSame($signer->sign('book', 12), $signer->sign('book', 12));
        $this->assertNotSame($signer->sign('book', 12), $signer->sign('book', 13));
        $this->assertNotSame($signer->sign('book', 12), $signer->sign('shop_product', 12));
    }

    // A payload rewritten by hand names nothing: without this the signature would be decoration, and the id would be back in the url
    public function testAPayloadTamperedWithIsRefused(): void
    {
        $signer = $this->signer();
        [, $signature] = explode('.', $signer->sign('book', 12));

        $this->assertNull($signer->unsign(rtrim(strtr(base64_encode('book:13'), '+/', '-_'), '=') . '.' . $signature));
    }

    // Another site's token, or this one's from before its secret changed
    public function testATokenSignedWithAnotherSecretIsRefused(): void
    {
        $this->assertNull($this->signer()->unsign(new ReviewTokenSigner('another-secret')->sign('book', 12)));
    }

    /**
     * Anything a route could be handed, answered the same way rather than fatally.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('malformedTokens')]
    public function testAMalformedTokenIsRefused(string $token): void
    {
        $this->assertNull($this->signer()->unsign($token));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'no separator' => ['Ym9vazoxMg'];
        yield 'two separators' => ['Ym9vazoxMg.aaaa.bbbb'];
        yield 'signature alone' => ['.aaaa'];
    }

    // A payload this site signed but holding something else than a type and an id - nothing a caller could resolve
    public function testASignedPayloadOfTheWrongShapeIsRefused(): void
    {
        $signer = $this->signer();

        foreach (['book', 'book:', ':12', 'Book:12', 'book:-1'] as $payload) {
            // Signed by this very signer, so only the shape of what it holds is left to refuse
            $this->assertNull($signer->unsign($this->reSign($payload)), sprintf('"%s" was accepted', $payload));
        }
    }

    // Signs a payload the way the signer does, the point here being a payload it would never have produced itself
    private function reSign(string $payload): string
    {
        $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = substr(rtrim(strtr(base64_encode(hash_hmac('sha256', $encoded, 'a-secret', true)), '+/', '-_'), '='), 0, 16);

        return $encoded . '.' . $signature;
    }

    private function signer(): ReviewTokenSigner
    {
        return new ReviewTokenSigner('a-secret');
    }
}
