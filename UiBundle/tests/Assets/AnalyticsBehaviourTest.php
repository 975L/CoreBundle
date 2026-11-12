<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Testing\JsCase;
use PHPUnit\Framework\Attributes\Group;

// assets/js/analytics.js and analytics-order.js with Matomo's driver, the instance never reached: what reaches its _paq queue is read back
#[Group('browser')]
class AnalyticsBehaviourTest extends JsCase
{
    // The page outlives a scenario, so the queue the previous one filled is dropped first
    private const array FRESH = ['before' => 'delete window._paq; document.querySelectorAll("script[src*=\\"matomo.js\\"]").forEach((s) => s.remove());'];

    // The page view counted against the instance the settings name, its script asked from there
    public function testThePageViewReachesTheProviderInUse(): void
    {
        $state = $this->observe(
            '<div data-controller="analytics" data-analytics-provider-value="matomo" data-analytics-settings-value=\'{"url":"http://127.0.0.1:9/stats","id":"3"}\'></div>',
            ['analytics' => 'analytics'],
            'await new Promise((r) => setTimeout(r, 200));

             return { queue: window._paq, script: [...document.scripts].map((s) => s.src).find((src) => src.includes("matomo.js")) };',
            self::FRESH,
        );

        $this->assertContains(['trackPageView'], $state['queue']);
        $this->assertContains(['setTrackerUrl', 'http://127.0.0.1:9/stats/matomo.php'], $state['queue']);
        $this->assertContains(['setSiteId', '3'], $state['queue']);
        $this->assertSame('http://127.0.0.1:9/stats/matomo.js', $state['script']);
    }

    // Turbo keeps the page across visits: each view names its own url, title and referrer, and the tracker and its script are set up once
    public function testEachTurboVisitCountsItsOwnPage(): void
    {
        $state = $this->observe(
            '<div></div>',
            [],
            'const settings = { url: "http://127.0.0.1:9/stats", id: "3" };
             const first = location.href;
             mod.matomo.default.start(settings);
             history.pushState({}, "", "/item-1");
             document.title = "Item 1";
             mod.matomo.default.start(settings);
             const second = location.href;
             history.pushState({}, "", first);

             return { queue: window._paq, first, second, scripts: [...document.scripts].filter((s) => s.src.includes("matomo.js")).length };',
            self::FRESH + ['modules' => ['matomo' => 'analytics-matomo']],
        );

        $this->assertContains(['setCustomUrl', $state['second']], $state['queue']);
        $this->assertContains(['setDocumentTitle', 'Item 1'], $state['queue']);
        $this->assertContains(['setReferrerUrl', $state['first']], $state['queue']);
        $this->assertCount(2, array_filter($state['queue'], static fn (array $call): bool => ['trackPageView'] === $call));
        $this->assertCount(1, array_filter($state['queue'], static fn (array $call): bool => 'setSiteId' === $call[0]));
        $this->assertSame(1, $state['scripts']);
    }

    // An order is handed in the neutral shape the component documents, and the driver turns it into Matomo's e-commerce calls
    public function testAnOrderReachesTheProviderInUse(): void
    {
        $order = htmlspecialchars(json_encode(['id' => 'A1', 'grandTotal' => 12.5, 'subTotal' => 10.0, 'tax' => 2.0, 'shipping' => 0.5, 'discount' => 0.0, 'items' => [['sku' => 'item-1', 'name' => 'Item', 'category' => 'catalog', 'price' => 10.0, 'quantity' => 1]]], \JSON_THROW_ON_ERROR), \ENT_QUOTES);

        $queue = $this->observe(
            \sprintf('<div data-controller="analytics-order" data-analytics-order-provider-value="matomo" data-analytics-order-order-value="%s"></div>', $order),
            ['analytics-order' => 'analytics-order'],
            'await new Promise((r) => setTimeout(r, 200));

             return window._paq;',
            self::FRESH,
        );

        $this->assertContains(['addEcommerceItem', 'item-1', 'Item', 'catalog', 10, 1], $queue);
        $this->assertContains(['trackEcommerceOrder', 'A1', 12.5, 10, 2, 0.5, 0], $queue);
    }

    // A provider with no driver measures nothing, and breaks nothing either
    public function testAnUnknownProviderMeasuresNothing(): void
    {
        $queue = $this->observe(
            '<div data-controller="analytics" data-analytics-provider-value="unknown" data-analytics-settings-value="{}"></div>',
            ['analytics' => 'analytics'],
            'await new Promise((r) => setTimeout(r, 200));

             return window._paq ?? null;',
            self::FRESH,
        );

        $this->assertNull($queue);
    }
}
