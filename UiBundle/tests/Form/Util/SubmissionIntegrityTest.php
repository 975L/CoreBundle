<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Util;

use c975L\UiBundle\Form\Util\SubmissionIntegrity;
use PHPUnit\Framework\TestCase;

class SubmissionIntegrityTest extends TestCase
{
    public function testAShortSubmissionIsComplete(): void
    {
        $this->assertFalse(SubmissionIntegrity::isTruncated(['title' => 'Home', 'blocks' => [['kind' => 'hero']]], 10));
    }

    // At the limit exactly, a complete body and one cut at its last accepted variable read the same - refusing it is the safe way round
    public function testASubmissionReachingTheLimitIsReadAsTruncated(): void
    {
        $this->assertTrue(SubmissionIntegrity::isTruncated(['a' => 1, 'b' => 2, 'c' => 3], 3));
    }

    // PHP counts every leaf it parses, however deep: a page's blocks, their slots and their medias all count
    public function testNestedLeavesAreCounted(): void
    {
        $submitted = ['blocks' => [
            ['kind' => 'section_cards', 'slots' => [['kind' => 'card'], ['kind' => 'card']]],
            ['kind' => 'hero'],
        ]];

        $this->assertTrue(SubmissionIntegrity::isTruncated($submitted, 4));
        $this->assertFalse(SubmissionIntegrity::isTruncated($submitted, 5));
    }

    public function testAnEmptySubmissionIsComplete(): void
    {
        $this->assertFalse(SubmissionIntegrity::isTruncated([], 1000));
    }

    // "max_input_vars = 0" is unlimited, and a value PHP could not read is no limit to measure against either
    public function testNoLimitIsNeverTruncated(): void
    {
        $this->assertFalse(SubmissionIntegrity::isTruncated(['a' => 1, 'b' => 2], 0));
        $this->assertFalse(SubmissionIntegrity::isTruncated(['a' => 1, 'b' => 2], -1));
    }

    // Read off php.ini when none is passed, which is how both callers use it
    public function testTheLimitDefaultsToTheIniSetting(): void
    {
        $limit = (int) ini_get('max_input_vars');
        $this->assertGreaterThan(0, $limit, 'This PHP reports no max_input_vars, the case below cannot be built.');

        $this->assertFalse(SubmissionIntegrity::isTruncated(['a' => 1]));
        $this->assertTrue(SubmissionIntegrity::isTruncated(array_fill(0, $limit, 'x')));
    }
}
