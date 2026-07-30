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

// Stimulus derives value names from the registered identifier, so a mismatch leaves siteKeyValue empty with nothing complaining
class CaptchaControllerDataAttributesTest extends TestCase
{
    private const CONTROLLER_JS = 'assets/js/captcha.js';
    private const CONTROLLERS_BARREL = 'assets/controllers.js';
    private const WIDGET_TWIG = 'templates/form/captcha_theme.html.twig';

    public function testTheControllerIsRegisteredUnderTheIdentifierTheWidgetUses(): void
    {
        $this->assertSame('captcha', $this->registeredIdentifier());
        $this->assertStringContainsString("'data-controller': 'captcha'", $this->read(self::WIDGET_TWIG));
    }

    public function testWidgetWritesEveryValueTheControllerDeclares(): void
    {
        $identifier = $this->registeredIdentifier();
        $twig = $this->read(self::WIDGET_TWIG);

        $values = $this->declaredValues($this->read(self::CONTROLLER_JS));
        $this->assertNotEmpty($values, sprintf('No "static values" found in "%s", the test itself is broken.', self::CONTROLLER_JS));

        foreach ($values as $value) {
            $attribute = sprintf('data-%s-%s-value', $identifier, $this->dasherize($value));
            $this->assertStringContainsString($attribute, $twig, sprintf('"%s" declares the "%s" value but "%s" never writes "%s".', self::CONTROLLER_JS, $value, self::WIDGET_TWIG, $attribute));
        }
    }

    // The identifier the barrel binds captcha.js to, eagerly or lazily - both spellings keep the same contract
    private function registeredIdentifier(): string
    {
        $barrel = $this->read(self::CONTROLLERS_BARREL);

        foreach (["/app\.register\('([a-z0-9-]+)', CaptchaController\)/", "/([a-z0-9-]+):\s*\(\)\s*=>\s*import\('\.\/js\/captcha\.js'\)/"] as $pattern) {
            if (preg_match($pattern, $barrel, $matches)) {
                return $matches[1];
            }
        }

        $this->fail(sprintf('captcha.js is not registered in "%s", under any identifier.', self::CONTROLLERS_BARREL));
    }

    // "static values = { siteKey: String, action: String }" -> ["siteKey", "action"]
    private function declaredValues(string $js): array
    {
        if (!preg_match('/static values\s*=\s*\{([^}]*)\}/', $js, $block)) {
            return [];
        }

        preg_match_all('/([A-Za-z0-9]+)\s*:/', $block[1], $matches);

        return $matches[1];
    }

    private function dasherize(string $value): string
    {
        return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $value));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
