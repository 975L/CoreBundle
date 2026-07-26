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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

// An alt="" is the standard way of marking an image decorative, but accessibility checks (see SiteBundle's
// ContentQualityClient) report it as a missing alternative rather than reading it as deliberate - which is
// how every icon of the site ended up flagged. The convention is therefore alt="" aria-hidden="true", both
// written together, and this locks it: a template writing a bare alt="" fails here.
class DecorativeImageAriaHiddenTest extends TestCase
{
    public function testEveryEmptyAltIsDoubledWithAriaHidden(): void
    {
        $found = 0;

        foreach ($this->templates() as $path) {
            // Twig comments document the components' own usage with [alt=""] samples, which are not markup
            $twig = (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents($path));

            $total = substr_count($twig, 'alt=""');
            $doubled = substr_count($twig, 'alt="" aria-hidden="true"');
            $found += $total;

            $this->assertSame($total, $doubled, sprintf('"%s" writes an alt="" that is not followed by aria-hidden="true".', $path));
        }

        $this->assertGreaterThan(0, $found, 'No decorative image found at all, the test itself is broken.');
    }

    // The JS-built icon grid has no template, so it is checked on the controller itself
    public function testIconPickerGridHidesItsIcons(): void
    {
        $js = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/js/icon-picker.js');

        $this->assertStringContainsString("img.setAttribute('aria-hidden', 'true')", $js);
    }

    // @return iterable<string>
    private function templates(): iterable
    {
        $dir = \dirname(__DIR__, 2) . '/templates';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.html.twig')) {
                yield $file->getPathname();
            }
        }
    }
}
