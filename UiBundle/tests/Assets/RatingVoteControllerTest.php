<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Service\RatingService;
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

    // Nothing is written to the browser before the visitor asks to vote: that is what keeps the widget out of consent territory, no identifier being created for someone who merely reads the page
    public function testConnectOnlyReadsTheBrowserStorage(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $connect = substr($controller, strpos($controller, 'connect()'), strpos($controller, 'async vote(') - strpos($controller, 'connect()'));

        $this->assertStringContainsString('this.read()', $connect);
        $this->assertStringNotContainsString('this.write(', $connect);
        $this->assertStringNotContainsString('newToken()', $connect);
    }

    // One vote at a time: a second click while the first is in flight would send a vote the server reads as another first one, and its insert breaks on the unique index the voter is held by
    public function testASecondClickIsIgnoredWhileAVoteIsInFlight(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('if (this.sending)', $controller);
        $this->assertStringContainsString('this.sending = true;', $controller);
        $this->assertStringContainsString('finally {', $controller);
    }

    // The token is minted on the click and not before, for the same reason
    public function testTheTokenIsMintedInsideTheVoteItself(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('store.token = store.token || this.newToken();', $controller);
    }

    // 32 hex characters, which is the only shape RatingService::resolveVoter() accepts from a browser
    public function testTheTokenIsShapedTheWayTheServerAccepts(): void
    {
        $this->assertStringContainsString('crypto.randomUUID().replace(/-/g, "")', $this->read(self::CONTROLLER_JS));
        $this->assertSame(32, \strlen(str_replace('-', '', '123e4567-e89b-12d3-a456-426614174000')));
        $this->assertSame(1, preg_match('/^[0-9a-f]{32}$/', str_replace('-', '', '123e4567-e89b-12d3-a456-426614174000')));
    }

    // A json body is half of what stands in for a csrf token (see RatingController): it is what sends a cross-origin caller through a preflight nothing answers
    public function testTheVoteIsSentAsJsonAndStaysOnThisOrigin(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('"Content-Type": "application/json"', $controller);
        $this->assertStringContainsString('credentials: "same-origin"', $controller);
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

    // A vote turned down for coming too fast says so, rather than joining every other failure under one label a visitor can do nothing with
    public function testAThrottledVoteIsToldApartFromEveryOtherFailure(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('throw new Error(String(response.status));', $controller);
        $this->assertStringContainsString('"429" === error.message ? this.throttledLabelValue : this.errorLabelValue', $controller);
    }

    // Storage a browser refuses (private mode, or a setting) must degrade to voting as a fresh visitor, never to a widget that throws
    public function testTheStorageIsReadAndWrittenDefensively(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertSame(3, substr_count($controller, 'try {'), 'Every browser storage access and the fetch itself are guarded');
        $this->assertStringContainsString('return { token: null, votes: {} };', $controller);
    }

    // A single icon is a "like", where the count is the whole answer - the same reading the template opens on
    public function testTheTallyDropsTheAverageOnASingleIcon(): void
    {
        $this->assertStringContainsString('if (1 === this.scaleValue) {', $this->read(self::CONTROLLER_JS));
    }

    // A compact widget prints the score and nothing else, the count being what a catalog card has no room for - the same reading the template opens on
    public function testACompactTallyDropsTheCount(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('compact: Boolean,', $controller);
        $this->assertStringContainsString('this.compactValue ? `${this.averageValue}/${this.scaleValue}`', $controller);
    }

    // The scale the widget was drawn with stays a display matter: sending it would let a forged vote store a score above what the site is rated out of, so the server reads the setting itself
    public function testTheScaleIsNotSentAlongWithTheVote(): void
    {
        $this->assertStringNotContainsString('scale: this.scaleValue', $this->read(self::CONTROLLER_JS));
        $this->assertGreaterThanOrEqual(RatingService::MIN_SCALE, RatingService::DEFAULT_SCALE);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/' . $path);
    }
}
