<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Entity;

use c975L\ConfigBundle\Entity\UrlMetadata;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlMetadataTest extends TestCase
{
    // The whole point of normalising on the way in: the stored path is compared to the one a request carries, so a row saved as "animaux" or "/animaux/" from the back office must still answer for "/animaux"
    #[DataProvider('paths')]
    public function testThePathIsStoredInTheFormARequestCarries(string $written, string $stored): void
    {
        $this->assertSame($stored, new UrlMetadata()->setPath($written)->getPath());
    }

    /** @return list<array{string, string}> */
    public static function paths(): array
    {
        return [
            ['/animaux', '/animaux'],
            ['animaux', '/animaux'],
            ['/animaux/', '/animaux'],
            ['animaux/', '/animaux'],
            ['/caste/guerrier/', '/caste/guerrier'],
            // The site root is the one path that keeps its slash, being nothing else
            ['/', '/'],
            ['', '/'],
        ];
    }

    // Listed by its path in the back office, where an admin recognises the url and not the row's id
    public function testItIsNamedByItsPath(): void
    {
        $this->assertSame('/animaux', (string) new UrlMetadata()->setPath('/animaux'));
        $this->assertSame('', (string) new UrlMetadata());
    }

    public function testItCarriesWhatTheUrlSaysOfItself(): void
    {
        $urlMetadata = new UrlMetadata()
            ->setPath('/animaux')
            ->setTitle('Animaux')
            ->setSummarySocialNetwork('Les douze compagnons des castes.');

        $this->assertSame('Animaux', $urlMetadata->getTitle());
        $this->assertSame('Les douze compagnons des castes.', $urlMetadata->getSummarySocialNetwork());
        $this->assertNull($urlMetadata->getOgImage());
    }
}
