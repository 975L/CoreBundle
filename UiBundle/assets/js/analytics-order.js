/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { loadDriver } from "./analytics.js";

// Hands a paid order to the provider in use (see components/Analytics/Order.html.twig), whose driver decides how it is counted once - a reload or a Turbo restore recording nothing twice is its business too
export default class extends Controller {
    static values = { provider: String, order: Object };

    async connect() {
        (await loadDriver(this.providerValue))?.trackOrder(this.orderValue);
    }
}
