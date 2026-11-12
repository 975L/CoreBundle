/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import * as consent from "./consent.js";

// One element, appended to the head, resolved on its own load event - a failure rejects, which draw() answers by leaving the list of places on screen
export function load(tag, attributes) {
    return new Promise((resolve, reject) => {
        const element = Object.assign(document.createElement(tag), attributes);
        element.onload = resolve;
        element.onerror = () => reject(new Error(`${tag} could not be loaded`));
        document.head.append(element);
    });
}

// Each map provider's browser side, by the slug its PHP provider answers (see MapProviderInterface): draw(controller) builds the map over the controller's canvas and points and answers it, blocks(uri) tells a Content-Security-Policy violation of its own
const DRIVERS = {
    leaflet: () => import("./map-leaflet.js"),
    google: () => import("./map-google.js"),
};

// Each provider's library, loaded once per document whatever the number of maps on the page, every controller waiting on the same promise
const libraries = {};

// Room left around the markers when the map frames itself on them, so a pin never sits against the edge of its box
export const BOUNDS_PADDING = 32;

// Only drawn once the box is about to enter the viewport: a map is a tile server (or ~500 kB of Google JavaScript) that a visitor scrolling past the section never needed
const ROOT_MARGIN = "200px";

// The side of a marker, shared by the pin sass/_map.scss draws and by an image a point names itself, so the two sorts sit at the same size on one map
export const PIN_SIZE = 24;

// Draws the places a "map" block holds with the provider the site named in "ui-map-provider" (see MapProviderInterface and Twig's ui_map_settings()), its driver doing the drawing. Nothing here is required for the block to hold: the list of places is rendered server-side and stays on screen under the map, which is also the only version of it a screen reader and a keyboard can work through - unless the map was drawn and the block offered a picker to find a marker through, which is this controller's to reveal in the list's place (see offerPicker)
export default class extends Controller {
    static targets = ["canvas", "list", "consent", "diagnostic", "select"];
    static values = {
        provider: String,
        // What the provider's driver draws with (MapProviderInterface::getSettings())
        settings: Object,
        needsConsent: Boolean,
        zoom: Number,
        points: Array,
        // A provider missing its key draws nothing, and the controller then only tells why to whoever may act on it
        usable: { type: Boolean, default: true },
    };

    connect() {
        this.onConsent = this.onConsent.bind(this);

        // A policy refusing the provider's host blocks the fetch and says so nowhere a script can read afterwards: this event is the only account of it, and it is listened for from the start because the load may begin as soon as the box is scrolled to
        this.refused = [];
        this.onViolation = this.onViolation.bind(this);
        document.addEventListener("securitypolicyviolation", this.onViolation);

        if (!this.usableValue) {
            this.tell("noKey");

            return;
        }

        if (!this.pointsValue.length) {
            return;
        }

        // A provider writing no cookie of its own is drawn at once. One that does waits for an accord, banner or not: consent.js keeps it for the visit on a page with no banner to ask through
        if (!this.needsConsentValue) {
            this.schedule();

            return;
        }

        if (consent.isAccepted("content")) {
            this.schedule();

            return;
        }

        this.consentTarget.hidden = false;
        this.listen();
    }

    // Turbo caches the page as it stands, and both the listeners and the drawn map would otherwise outlive the element they belong to
    disconnect() {
        document.removeEventListener("securitypolicyviolation", this.onViolation);
        this.stopListening();
        this.observer?.disconnect();
        this.map?.remove?.();
        this.map = null;
    }

    // The room the map will take, taken before the library is asked for: revealed at the end of the load, the canvas and the dropped list reflowed the page under its reader - given back by restore() only if the library never arrives
    reserve() {
        this.canvasTarget.hidden = false;
        this.offerPicker();
    }

    // The block as it was rendered, for a map that could not be drawn: the empty box goes, and the places are written out again rather than held behind a picker pointing at no marker
    restore() {
        this.canvasTarget.hidden = true;

        if (!this.hasSelectTarget) {
            return;
        }

        this.selectTarget.hidden = true;
        this.listTarget.hidden = false;
    }

    // The places, handed to the browser's own picker instead of written out one under the other - dozens of them are a page of their own, and a name is reached there by typing its first letters. Never offered server-side: the picker points at a marker, so a visitor whose JavaScript never ran keeps the written-out list whole
    offerPicker() {
        if (!this.hasSelectTarget) {
            return;
        }

        this.selectTarget.hidden = false;
        this.listTarget.hidden = true;
    }

    // The place just picked, found on the map rather than opened, its popup carrying the link to the place's page. The first option names no place and takes the map back onto the whole listing
    locate() {
        if (!this.selectTarget.value) {
            this.reframe?.();

            return;
        }

        // Picked while the library is still on its way: kept, and played back by locateEarlyPick() once the markers exist
        if (!this.locator) {
            this.pendingPlace = Number(this.selectTarget.value);

            return;
        }

        this.locator(Number(this.selectTarget.value));
    }

    // The place picked before the map was drawn, found now that it is - the picker is on screen from the start (see reserve), and a choice made in that window would otherwise be lost
    locateEarlyPick() {
        if (null === (this.pendingPlace ?? null)) {
            return;
        }

        this.locator(this.pendingPlace);
        this.pendingPlace = null;
    }

    // Through the banner's adapter (see consent.js), whatever banner the site runs
    listen() {
        this.stopListening();
        this.unlisten = consent.onChange(this.onConsent);
    }

    stopListening() {
        this.unlisten?.();
        this.unlisten = null;
    }

    accept() {
        consent.accept("content");
    }

    onConsent() {
        if (consent.isAccepted("content")) {
            this.consentTarget.hidden = true;
            this.schedule();
        }
    }

    // The banner announces the choice on every load once it is known, so a returning visitor gets the map without clicking again - and a map already drawn is not drawn a second time
    schedule() {
        this.stopListening();

        if (this.map) {
            return;
        }

        this.reserve();

        if (!("IntersectionObserver" in window)) {
            this.draw();

            return;
        }

        this.observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                this.observer.disconnect();
                this.draw();
            }
        }, { rootMargin: ROOT_MARGIN });
        this.observer.observe(this.element);
    }

    // The provider's driver, null for one this bundle has no driver for - which draw() answers like any other failure
    async driver() {
        const driver = DRIVERS[this.providerValue];
        this.loaded ??= driver ? (await driver()).default : null;

        return this.loaded;
    }

    // The provider's library, cleared on failure so a later map may try again - a single failed fetch must not leave every map on the site refusing to draw for the rest of the visit
    library(driver) {
        libraries[this.providerValue] ??= driver.load(this.settingsValue).catch((error) => {
            delete libraries[this.providerValue];

            throw error;
        });

        return libraries[this.providerValue];
    }

    // A failure of any kind gives the room back and puts the list of places on screen. The canvas is already visible when the library gets it (see reserve): every provider reads its size as it builds, and a display:none box paints a grey grid
    async draw() {
        try {
            const driver = await this.driver();
            if (!driver) {
                throw new Error(`Unknown map provider "${this.providerValue}".`);
            }
            this.map = driver.draw(this, await this.library(driver));
        } catch {
            this.restore();
            await this.explain();
        }
    }

    // Every violation is kept, the map's own being told apart once the driver is known (see explain): one may be raised before the load is even attempted
    onViolation(event) {
        this.refused.push({ uri: event.blockedURI, directive: event.effectiveDirective || event.violatedDirective });
    }

    // Why the map is not there, in place, for whoever may do something about it - a visitor is shown the list of places and told nothing about a setting they cannot change
    // Only what stopped the library from loading at all: a map that draws over a policy refusing its tiles is a grey grid nothing here can see, and that one is the health check's to raise (see MapCspHealthCheckProvider)
    async explain() {
        // The violation is queued rather than raised in front of the failing load, so it is read a tick later
        await new Promise((resolve) => setTimeout(resolve, 0));

        // Only the provider's own hosts: a violation raised by anything else on the page - a font, another embed - is not this block's to report
        const refused = this.refused.find((violation) => this.loaded?.blocks(violation.uri));
        this.tell(refused ? "csp" : "refused", refused?.directive);
    }

    // The sentences live outside the cached block, rendered by the layout for the back-office alone (see components/Map/_diagnostic.html.twig): on a visitor's page there are none, and nothing is said
    tell(reason, directive = "") {
        const sentence = document.getElementById("ui-map-diagnostic")?.dataset[reason];
        if (!sentence || !this.hasDiagnosticTarget) {
            return;
        }

        this.diagnosticTarget.textContent = sentence.replace("%directive%", directive);
        this.diagnosticTarget.hidden = false;
    }

    // A single place keeps the zoom the editor chose - framing on one marker's bounds would zoom the map as far in as it goes, on a street corner nobody can place
    frame(count, fit) {
        if (count > 1) {
            fit();
        }
    }

    // Built as elements and not as an HTML string: the label and the text are what an editor typed, and the address a marker sits at is no reason for them to reach a popup unescaped
    popup(point) {
        const container = document.createElement("div");
        container.className = "ui-map__popup";

        const heading = document.createElement(point.url ? "a" : "strong");
        heading.textContent = point.label;
        if (point.url) {
            heading.href = point.url;
        }
        container.append(heading);

        if (point.text) {
            const text = document.createElement("p");
            text.textContent = point.text;
            container.append(text);
        }

        return container;
    }


}
