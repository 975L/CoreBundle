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

// Locks the identifier, the classes and the icon paths together, none of which fails loudly on its own
class PasswordControllerAssetsTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/password.js';
    private const string CONTROLLERS_BARREL = 'assets/controllers.js';
    private const array CSS_CLASSES = ['has-toggle', 'toggle-password', 'error-message', 'error', 'success'];

    public function testTheControllerIsRegisteredInTheBarrel(): void
    {
        $this->assertSame('password', $this->registeredIdentifier());
    }

    public function testEveryIconTheControllerPointsAtExists(): void
    {
        $script = $this->read(self::CONTROLLER_JS);

        // The icon file name is concatenated onto a path constant, so the two halves are picked up apart
        preg_match('#ICON_PATH\s*=\s*["\']/bundles/([a-z0-9]+)/([^"\']*)["\']#', $script, $base);
        $this->assertNotEmpty($base, sprintf('"%s" declares no ICON_PATH, the test itself is broken.', self::CONTROLLER_JS));
        $this->assertSame('c975lui', $base[1], sprintf('"%s" still points at another bundle for its icons.', self::CONTROLLER_JS));

        // Both halves are read whether the file name is interpolated into a template literal or concatenated, so a mix of the two leaves no icon unchecked
        preg_match_all('/ICON_PATH(?:\}([^`]+)`|\s*\+\s*["\']([^"\']+)["\'])/', $script, $icons);
        $names = array_filter(array_merge($icons[1], $icons[2]));
        $this->assertNotEmpty($names, sprintf('"%s" builds no icon path off ICON_PATH.', self::CONTROLLER_JS));

        foreach (array_unique($names) as $icon) {
            $path = \dirname(__DIR__, 2) . '/public/' . $base[2] . $icon;
            $this->assertFileExists($path, sprintf('"%s" points at "%s%s", which this bundle does not ship.', self::CONTROLLER_JS, $base[2], $icon));
        }
    }

    public function testEveryClassTheControllerWritesIsStyled(): void
    {
        $script = $this->read(self::CONTROLLER_JS);
        $css = $this->read('public/css/styles.css');

        foreach ($this->classesWrittenBy($script) as $class) {
            $this->assertContains($class, self::CSS_CLASSES, sprintf('"%s" writes the unexpected class "%s" - add it to sass/_forms.scss and to this test.', self::CONTROLLER_JS, $class));
            $this->assertMatchesRegularExpression('/\.' . preg_quote($class, '/') . '\b/', $css, sprintf('"%s" writes the "%s" class but the compiled stylesheet has no rule for it.', self::CONTROLLER_JS, $class));
        }
    }

    // classList.add("has-toggle") / classList.toggle("error", ...) -> ["has-toggle", "error"]
    private function classesWrittenBy(string $script): array
    {
        preg_match_all('/classList\.(?:add|toggle|remove)\(\s*["\']([a-z0-9_-]+)["\']/i', $script, $matches);

        return array_values(array_unique($matches[1]));
    }

    // The identifier the barrel binds password.js to, eagerly or through the LAZY_CONTROLLERS map
    private function registeredIdentifier(): string
    {
        $barrel = $this->read(self::CONTROLLERS_BARREL);

        foreach (["/app\.register\('([a-z0-9-]+)', PasswordController\)/", "/([a-z0-9-]+):\s*\(\)\s*=>\s*import\('\.\/js\/password\.js'\)/"] as $pattern) {
            if (preg_match($pattern, $barrel, $matches)) {
                return $matches[1];
            }
        }

        $this->fail(sprintf('password.js is not registered in "%s", under any identifier.', self::CONTROLLERS_BARREL));
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
