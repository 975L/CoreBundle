<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;

// The failed-message table truncates its cells, an exception message running to several lines otherwise pushing the table past the screen. Those widths used to be style="" attributes, which the EasyAdmin layout's nonce on style-src drops - so they are classes now, and a class nothing declares truncates nothing without a single error to show for it.
class FailedMessagesCellWidthTest extends TestCase
{
    private const array CSS = ['/public/css/management.css', '/public/css/management.min.css'];

    private const array WIDTHS = [
        'failed-message-cell' => '12rem',
        'failed-message-cell-wide' => '18rem',
        'failed-message-cell-group' => '32rem',
    ];

    public function testEveryTruncatedCellClassCarriesItsWidth(): void
    {
        $template = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/management/messenger_failed_index.html.twig');

        $this->assertStringNotContainsString('style="', $template, 'The table writes a width as an attribute again, which a nonced style-src drops.');

        foreach (self::WIDTHS as $class => $width) {
            $this->assertStringContainsString('"text-break ' . $class . '"', $template, 'No cell wears .' . $class . ' any more, this expectation is stale.');

            foreach (self::CSS as $file) {
                $this->assertStringContainsString('.' . $class . '{max-width:' . $width . '', $this->css($file), sprintf('%s declares no width for .%s, so the cell is no longer truncated.', $file, $class));
            }
        }
    }

    // Same shape whichever of the two stylesheets it comes from - only the space around the punctuation differs
    private function css(string $file): string
    {
        $css = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(\dirname(__DIR__, 2) . $file));

        return (string) preg_replace('#\s*([:;{},/])\s*#', '$1', $css);
    }
}
