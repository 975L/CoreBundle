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

// Without it iOS Safari takes playback fullscreen, which breaks any layout counting on the video where it sits - so it is not a per-component decision but a rule every <video> this bundle writes follows
class VideoPlaysInlineTest extends TestCase
{
    public function testEveryVideoTagCarriesPlaysInline(): void
    {
        $checked = 0;
        foreach ($this->videoLines() as $file => $lines) {
            foreach ($lines as $number => $line) {
                ++$checked;
                $this->assertStringContainsString('playsinline', $line, sprintf('"%s" line %d writes a <video> without "playsinline", which iOS Safari plays fullscreen.', $file, $number));
            }
        }

        $this->assertGreaterThan(0, $checked, 'No <video> tag found at all, the test itself is broken.');
    }

    private function videoLines(): array
    {
        $found = [];
        $directory = new \RecursiveDirectoryIterator(\dirname(__DIR__, 2) . '/templates');
        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.html.twig')) {
                continue;
            }

            $lines = explode("\n", (string) file_get_contents($file->getPathname()));
            foreach ($lines as $index => $line) {
                if (str_contains($line, '<video ')) {
                    $found[$file->getFilename()][$index + 1] = $line;
                }
            }
        }

        return $found;
    }
}
