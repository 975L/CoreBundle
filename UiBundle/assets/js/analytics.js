/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Each audience measurement provider's browser side, by the slug its PHP provider answers (see AnalyticsProviderInterface): start(settings) counts the page view, trackOrder(order) a paid order
export const DRIVERS = {
    matomo: () => import("./analytics-matomo.js"),
};

// Loads the driver of a provider, null for one this bundle has no driver for, which then measures nothing rather than failing the page
export async function loadDriver(slug) {
    const driver = DRIVERS[slug];

    return driver ? (await driver()).default : null;
}

// Starts the provider in use on the page view (see components/Analytics/Tracker.html.twig). Generic: what is sent and where is the driver's business
export default class extends Controller {
    static values = { provider: String, settings: Object };

    async connect() {
        (await loadDriver(this.providerValue))?.start(this.settingsValue);
    }
}
