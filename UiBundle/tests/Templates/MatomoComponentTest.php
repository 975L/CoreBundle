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

// Audience measurement came from c975L/SiteBundle, which declared the three keys while this bundle's cookies model was already reading one of them to offer Matomo's opt-out link
// Three ends drift silently here, none of them raising an error: a template no longer guarded is rendered on a site that turned Matomo off, an identifier no longer registered leaves the div inert, and a dataset name no longer matching what the controller reads sends every page view to "undefined"
class MatomoComponentTest extends TestCase
{
    private const string COMPONENT = 'templates/components/Analytics/Matomo.html.twig';
    private const string CONTROLLER_JS = 'assets/js/matomo.js';
    private const string BARREL = 'assets/controllers.js';

    // The guard belongs here rather than in each layout calling it, or a caller forgetting it tracks a site that asked not to be
    public function testTheComponentCarriesItsOwnGuard(): void
    {
        $this->assertStringContainsString("{% if config('site-enable-matomo') %}", $this->read(self::COMPONENT));
    }

    // Both values are needed for a tracker url to be built at all, so a half-filled configuration renders nothing rather than a broken request
    public function testNothingIsRenderedWithoutBothValues(): void
    {
        $this->assertStringContainsString('{% if matomoUrl and matomoId %}', $this->read(self::COMPONENT));
    }

    // The dataset names are the contract with the controller, which reads them off the element rather than through Stimulus values
    public function testTheDatasetNamesMatchWhatTheControllerReads(): void
    {
        $component = $this->read(self::COMPONENT);
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('data-controller="matomo"', $component);

        foreach (['url' => 'matomoUrl', 'id' => 'matomoId'] as $attribute => $property) {
            $this->assertStringContainsString(\sprintf('data-matomo-%s="', $attribute), $component);
            $this->assertStringContainsString(\sprintf('dataset.%s', $property), $controller);
        }
    }

    // Lazily registered, so it only loads on a page actually rendering the component - which is also why the identifier has to match the one written above, the barrel looking the elements up by it
    public function testTheControllerIsRegisteredLazilyUnderTheSameIdentifier(): void
    {
        $this->assertStringContainsString("matomo: () => import('./js/matomo.js')", $this->read(self::BARREL));
    }

    // The keys travelled with the component: declared where they are read, and in the group the cookie banner already sits in
    public function testTheThreeKeysAreDeclaredHere(): void
    {
        $declared = [];

        foreach (json_decode($this->read('config/configs.json'), true, 512, \JSON_THROW_ON_ERROR) as $entry) {
            $declared[$entry['slug']] = $entry['group'];
        }

        foreach (['site-matomo-url', 'site-matomo-id', 'site-enable-matomo'] as $slug) {
            $this->assertArrayHasKey($slug, $declared, \sprintf('"%s" is no longer declared by the bundle reading it.', $slug));
            $this->assertSame('analytics', $declared[$slug], \sprintf('"%s" left the group holding the cookie banner.', $slug));
        }
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
