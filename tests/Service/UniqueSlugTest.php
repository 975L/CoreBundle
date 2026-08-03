<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\UniqueSlug;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

// Moved here from SiteBundle's PageCrudController, where the algorithm used to live in a trait - every bundle with a slugged entity needs it
class UniqueSlugTest extends TestCase
{
    // $collides answering false straight away leaves the normalized base untouched
    public function testReturnsTheBaseSlugWhenAvailable(): void
    {
        $this->assertSame('my-page', UniqueSlug::build(new AsciiSlugger(), 'My Page', static fn (): bool => false));
    }

    // Accents, spaces and case are normalized before any collision is even looked up
    public function testNormalizesAccentsSpacesAndCase(): void
    {
        $this->assertSame('hello-world', UniqueSlug::build(new AsciiSlugger(), 'Héllo Wörld', static fn (): bool => false));
    }

    // Appends -2, -3... until the candidate is free
    public function testAppendsASuffixOnCollision(): void
    {
        $taken = ['my-page' => true, 'my-page-2' => true];

        $this->assertSame('my-page-3', UniqueSlug::build(new AsciiSlugger(), 'My Page', static fn (string $candidate): bool => isset($taken[$candidate])));
    }

    // The scope the collision is checked against is the caller's business: the same base can be free in one group and taken in another
    public function testTheCollisionCallbackDecidesTheScope(): void
    {
        $takenInThisGroup = ['my-item' => true];

        $this->assertSame('my-item-2', UniqueSlug::build(new AsciiSlugger(), 'My Item', static fn (string $candidate): bool => isset($takenInThisGroup[$candidate])));
        $this->assertSame('my-item', UniqueSlug::build(new AsciiSlugger(), 'My Item', static fn (): bool => false));
    }
}
