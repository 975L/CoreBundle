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

// The cover laid over a third-party video until consent is given: a box in the video's ratio, so drawn as a panel
class VideoConsentRadiusTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'styles.css' => ['styles.css'],
            'styles.min.css' => ['styles.min.css'],
        ];
    }

    // A panel's radius and not a button's: the box spans the whole width of the media, and a theme drawing its buttons as pills (--radius-btn: 999px) turned it into a large oval in the middle of the page
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheConsentPlaceholderIsRoundedLikeAPanelAndNotLikeAButton(string $file): void
    {
        $css = $this->normalize($file);

        $this->assertStringContainsString('.video-iframe-placeholder{', $css, sprintf('"%s" no longer draws the consent placeholder at all.', $file));
        $this->assertMatchesRegularExpression(
            '/\.video-iframe-placeholder\{[^}]*border-radius:var\(--radius-panel\)/',
            $css,
            sprintf('"%s" gives the consent placeholder a button radius, which a pill-shaped theme turns into an oval.', $file)
        );
    }

    // The prompt panel laid over the poster follows the same rule, for the same reason
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testThePromptOverAPosterIsRoundedLikeAPanelToo(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.video-iframe-placeholder--poster\.video-iframe-prompt\{[^}]*border-radius:var\(--radius-panel\)/',
            $this->normalize($file),
            sprintf('"%s" gives the consent prompt a button radius.', $file)
        );
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function normalize(string $file): string
    {
        $path = \dirname(__DIR__, 2) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s+/', '', $css);
    }
}
