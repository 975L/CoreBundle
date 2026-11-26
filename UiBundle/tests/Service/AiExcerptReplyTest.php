<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\AiExcerptReply;
use PHPUnit\Framework\TestCase;

// A model's answer read back against the excerpts it was given, its links taken from them alone
class AiExcerptReplyTest extends TestCase
{
    private const array EXCERPTS = [
        ['url' => '/catalog', 'title' => 'Catalog', 'content' => 'item-1'],
        ['url' => '/catalog', 'title' => 'Catalog', 'content' => 'item-2'],
        ['url' => '/contact', 'title' => 'Contact', 'content' => 'form'],
    ];

    // The numbers named map back to their excerpts, one link per url, a number naming none dropped
    public function testNumbersMapBackToTheExcerpts(): void
    {
        [$answer, $sources] = AiExcerptReply::parse('Here: {"answer": "<b>Yes</b>", "sources": [1, 2, 9, "x"]}', self::EXCERPTS);

        $this->assertSame('Yes', $answer);
        $this->assertSame([['url' => '/catalog', 'title' => 'Catalog']], $sources);
    }

    // A plain reply naming its excerpts by their marks keeps only those, its marks and its sources line taken off
    public function testPlainReplyNamingItsExcerpts(): void
    {
        [$answer, $sources] = AiExcerptReply::parse("The form is on the contact page [3].\nSources: [3]", self::EXCERPTS);

        $this->assertSame('The form is on the contact page.', $answer);
        $this->assertSame([['url' => '/contact', 'title' => 'Contact']], $sources);
    }

    // A reply that isn't the JSON asked for is the answer, every excerpt its source
    public function testPlainReplyKeepsEveryExcerpt(): void
    {
        [$answer, $sources] = AiExcerptReply::parse('Just text', self::EXCERPTS);

        $this->assertSame('Just text', $answer);
        $this->assertSame(['/catalog', '/contact'], array_column($sources, 'url'));
    }
}
