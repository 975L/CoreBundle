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

// EasyAdmin's layout never sets data-controller itself, so the controller only ever connects through the barrel's own body mount, and the query param it reads is what other bundles link with - each of those three ends drifts silently, the repository having no browser to run the controller in
class FieldFocusControllerRegistrationTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/field-focus.js';
    private const string ADMIN_BARREL = 'assets/controllers-admin.js';
    private const string IDENTIFIER = 'fieldFocus';

    public function testTheControllerIsRegisteredInTheAdminBarrel(): void
    {
        $barrel = $this->read(self::ADMIN_BARREL);

        $this->assertStringContainsString("import FieldFocusController from './js/field-focus.js';", $barrel);
        $this->assertStringContainsString(sprintf("app.register('%s', FieldFocusController);", self::IDENTIFIER), $barrel);
    }

    // Registering it is not enough: nothing in the back-office writes data-controller, the barrel mounts it on <body> itself
    public function testTheIdentifierIsMountedOnBody(): void
    {
        $this->assertMatchesRegularExpression(
            sprintf("/document\.body\.setAttribute\(\s*'data-controller',\s*\[[^\]]*'%s'[^\]]*\]/s", self::IDENTIFIER),
            $this->read(self::ADMIN_BARREL),
            sprintf('"%s" is registered but never added to the <body> mount, so it never connects.', self::IDENTIFIER)
        );
    }

    // The param name is a cross-bundle contract - SiteBundle's page health check advice builds its links with it
    public function testItReadsTheFocusFieldQueryParam(): void
    {
        $this->assertStringContainsString("get('focusField')", $this->read(self::CONTROLLER_JS));
    }

    // EasyAdmin names every field "<FormName>[<property>]", the form name varying with the entity, so the lookup matches on the suffix alone
    public function testTheFieldIsLookedUpByNameSuffix(): void
    {
        $this->assertStringContainsString('[name$="[${field}]"]', $this->read(self::CONTROLLER_JS));
    }

    // A CollectionField prints neither name nor id of its own, so the suffix match never finds one: the fallback reads the property name off its entries, then off its prototype when it holds none
    public function testACollectionIsLookedUpByItsEntriesAndItsPrototype(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('[name*="[${field}]["]', $controller);
        $this->assertStringContainsString('[data-prototype*="[${field}]["]', $controller);
        $this->assertStringContainsString("closest('[data-ea-collection-field]')", $controller);
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
