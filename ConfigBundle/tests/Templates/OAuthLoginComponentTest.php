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

// The login page reaches the OAuth buttons through a component name, which nothing resolves at compile time: rename the template's directory and the page keeps rendering, minus its buttons and without a single error to show for it. The name and the file are checked against each other here.
class OAuthLoginComponentTest extends TestCase
{
    private const string COMPONENT_TAG = '<twig:c975LConfig:Security:OAuthLogin/>';

    private function component(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/components/Security/OAuthLogin.html.twig');
    }

    // The scaffold is copied into every site, so this one line is the whole of what installing this feature changes there
    public function testTheScaffoldedLoginPageRendersTheComponent(): void
    {
        $login = (string) file_get_contents(\dirname(__DIR__, 2) . '/scaffold/templates/security/login.html.twig');

        $this->assertStringContainsString(self::COMPONENT_TAG, $login);
    }

    // c975LConfig:Security:OAuthLogin is templates/components/Security/OAuthLogin.html.twig and nothing else
    public function testTheComponentNameMatchesAFileThatExists(): void
    {
        $this->assertFileExists(\dirname(__DIR__, 2) . '/templates/components/Security/OAuthLogin.html.twig');
    }

    // Anonymous like every component of this ecosystem, so what it displays comes from a Twig function rather than from a class it can't have
    public function testTheComponentReadsTheProvidersAndLinksToTheirRoute(): void
    {
        $component = $this->component();

        $this->assertStringContainsString('oauth_login_providers()', $component);
        $this->assertStringContainsString("path('config_oauth_connect'", $component);
        $this->assertStringContainsString('{provider: provider.key}', $component);
        $this->assertStringContainsString("'label.continue_with'|trans({'%provider%': provider.name}, 'config')", $component);
    }

    // Where the visitor comes back to once signed in - the order they just paid for, when PaymentBundle's invitation is what they clicked. Left out, they land wherever a form login would have taken them
    public function testTheComponentCarriesAnOptionalRedirect(): void
    {
        $component = $this->component();

        $this->assertStringContainsString("{% set redirect = redirect|default('') %}", $component);
        $this->assertStringContainsString('{provider: provider.key, redirect: redirect}', $component);
    }

    // A site that configured no provider must render nothing at all, its login page staying exactly as it was
    public function testTheComponentRendersNothingWithoutAProvider(): void
    {
        $this->assertStringContainsString('{% if providers is not empty %}', $this->component());
    }

    // A nonced style-src drops style attributes, and the mark is inlined rather than served as a file precisely to need no source of its own
    public function testTheComponentCarriesNoInlineStyleAndNoRemoteAsset(): void
    {
        $component = $this->component();

        $this->assertStringNotContainsString('style="', $component);
        $this->assertStringNotContainsString('http', $component);
    }
}
