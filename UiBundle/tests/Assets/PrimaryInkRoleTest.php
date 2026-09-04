<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// --primary is the brand color as a fill, painted on the page; --primary-ink is that same color as ink, read against it. The two part company in dark mode, where SiteBundle lightens the ink and leaves the fill its hue, so a rule writing text, an outline or a rule with --primary stays the dark brand hue on a dark ground. Eleven such roles shipped that way at once, and only the ones a reviewer happened to open were found - hence this scan of the whole sass directory. It looks at the properties that draw ink and nothing else: a background reading --primary is the token doing its job.
class PrimaryInkRoleTest extends TestCase
{
    // The properties that put a color on the page as ink rather than as a fill
    private const string INK_PROPERTIES = 'color|outline|outline-color|border(?:-[a-z-]+)?|text-decoration-color|text-emphasis-color|column-rule-color|caret-color|fill|stroke|-webkit-text-fill-color';

    // Declarations reading --primary on purpose, listed in full so an accidental one never hides behind a line number
    private const array ALLOWED = [
        // The label of a "primary" CTA on a colored flat, which inverts to a stated #fff that DarkGroundInkTest locks. The brand hue is read against white in either mode, which is what --primary is
        'color: var(--section-btn-color, var(--primary));',
    ];

    // Every ink role goes through --primary-ink, so a design lightening its dark mode reaches all of them at once
    public function testNoInkRoleReadsThePrimaryFill(): void
    {
        $scanned = 0;
        $offences = [];
        $root = \strlen(\dirname(__DIR__, 2) . '/sass/');

        foreach ($this->sassFiles() as $path) {
            $lines = explode("\n", (string) file_get_contents($path));
            foreach ($lines as $number => $line) {
                if (1 !== preg_match('/^\s*(' . self::INK_PROPERTIES . ')\s*:\s*(.+?;)(?:\s*\/\/.*)?\s*$/', $line, $match)) {
                    continue;
                }

                ++$scanned;
                // Rebuilt from the property and its value rather than read off the line, so a trailing "//" comment neither hides an offence nor keeps a declaration out of ALLOWED
                $declaration = $match[1] . ': ' . $match[2];
                if (!str_contains($match[2], 'var(--primary)') || in_array($declaration, self::ALLOWED, true)) {
                    continue;
                }

                $offences[] = sprintf('sass/%s:%d — %s', substr($path, $root), $number + 1, $declaration);
            }
        }

        $this->assertGreaterThan(100, $scanned, 'No ink declaration found under sass/: the directory moved, and this test would pass blind.');

        $this->assertSame([], $offences, sprintf(
            "These write, outline or rule with the --primary fill instead of the --primary-ink token, so they stay the dark brand hue on a dark ground:\n- %s",
            implode("\n- ", $offences)
        ));
    }

    /**
     * Walked rather than globbed, so "sass/management/" and any directory opened later are covered too.
     *
     * @return string[]
     */
    private function sassFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__, 2) . '/sass'));

        foreach ($iterator as $file) {
            // "emails/" is left out: no mail client offers a dark mode, and "resolve_css_variables" substitutes every var() with a literal before the mail is sent, so the two roles never part company there
            if ('scss' === $file->getExtension() && !str_contains($file->getPathname(), '/sass/emails/')) {
                $files[] = $file->getPathname();
            }
        }

        $this->assertNotSame([], $files, 'No stylesheet found under sass/: the directory moved, and this test would pass blind.');

        return $files;
    }
}
