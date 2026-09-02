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

// The visitor's own vote lives in their browser and nowhere else, the page it is cast from being cached and shared. What that costs is checked across three files no browser runs here, so each end is held to what the other two assume
class RatingVoteControllerTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/rating.js';
    private const string BARREL = 'assets/controllers.js';
    private const string COMPONENT = 'templates/components/Rating/Rating.html.twig';

    // Public pages only, and lazily: the barrel imports it for a document that actually holds a widget
    public function testTheControllerIsRegisteredAsALazyFrontControllerNamedAfterWhatTheTemplateWrites(): void
    {
        $this->assertStringContainsString("'ui-rating': () => import('./js/rating.js'),", $this->read(self::BARREL));
        $this->assertStringContainsString('data-controller="ui-rating"', $this->read(self::COMPONENT));
    }

    // The identifier is registered in kebab-case on purpose: Stimulus derives every "data-ui-rating-*-value" name from it, and a camelCase one would silently bind none of them
    public function testTheIdentifierIsTheOneEveryValueAttributeIsDerivedFrom(): void
    {
        $this->assertStringContainsString("'ui-rating':", $this->read(self::BARREL));
        $this->assertStringNotContainsString('uiRating:', $this->read(self::BARREL));
    }

    // Every value and target the template writes is one the controller declares, and the other way round
    public function testTheValuesAndTargetsMatchTheComponent(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);
        $component = $this->read(self::COMPONENT);

        $this->assertStringContainsString('static targets = ["row", "star", "tally"];', $controller);
        foreach (['row', 'star', 'tally'] as $target) {
            $this->assertStringContainsString('data-ui-rating-target="' . $target . '"', $component);
        }

        foreach (['url', 'key', 'scale', 'count', 'average', 'noneLabel', 'oneLabel', 'manyLabel', 'errorLabel', 'throttledLabel'] as $value) {
            $this->assertMatchesRegularExpression('/^\s+' . $value . ':/m', $controller, sprintf('The controller declares no "%s" value', $value));
            $attribute = strtolower((string) preg_replace('/([A-Z])/', '-$1', $value));
            $this->assertStringContainsString('data-ui-rating-' . $attribute . '-value="', $component);
        }
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/' . $path);
    }
}
