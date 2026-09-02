/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Same contract as video-iframe.js, and matched on both spellings for the same reason: whoever provides the banner chooses the Stimulus identifier, and a missed match would let a Google map load before any consent has been given
const CONSENT_BANNER_SELECTOR = '[data-controller~="cookie-consent"], [data-controller~="cookieConsent"]';

// Google's loader takes a global callback, so the script is appended once per document and every controller on the page waits on the same promise
const GOOGLE_CALLBACK = "__c975lUiMapGoogleReady";
let googleLoader = null;

// Leaflet is served by this bundle rather than fetched from a package the app declares, so it is appended the same way and once per document too (see cookie-consent.js, which vendors its own banner for the same reason)
let leafletLoader = null;

// Room left around the markers when the map frames itself on them, so a pin never sits against the edge of its box
const BOUNDS_PADDING = 32;

// Only drawn once the box is about to enter the viewport: a map is a tile server (or ~500 kB of Google JavaScript) that a visitor scrolling past the section never needed
const ROOT_MARGIN = "200px";

// Draws the places a "map" block holds, over OpenStreetMap tiles or over Google's API, the site having said which in "ui-map-provider" (see MapProvider and Twig's ui_map_settings()). Nothing here is required for the block to hold: the list of places is rendered server-side and stays on screen under the map, which is also the only version of it a screen reader and a keyboard can work through
export default class extends Controller {
    static targets = ["canvas", "list", "consent", "diagnostic"];
    static values = {
        provider: String,
        apiKey: String,
        // Defaults match the copies shipped in this bundle's public/ - the component passes the asset()-resolved (cache-busted) urls, these are only the fallback when it doesn't
        script: { type: String, default: "/bundles/c975lui/js/leaflet.js" },
        stylesheet: { type: String, default: "/bundles/c975lui/css/leaflet.css" },
        tileUrl: String,
        attribution: String,
        needsConsent: Boolean,
        zoom: Number,
        points: Array,
        // Rendered for whoever may act on the setting and for nobody else (see components/Map/Map.html.twig), so both are empty on a visitor's page
        diagnostic: String,
        diagnosticCsp: String,
    };

    connect() {
        this.onConsent = this.onConsent.bind(this);

        // A policy refusing Google's host blocks the fetch and says so nowhere a script can read afterwards: this event is the only account of it, and it is listened for from the start because the load may begin as soon as the box is scrolled to
        this.refused = [];
        this.onViolation = this.onViolation.bind(this);
        document.addEventListener("securitypolicyviolation", this.onViolation);

        if (!this.pointsValue.length) {
            return;
        }

        // A provider writing no cookie of its own, or a page with no banner to ask through - never hold content back on a site that asks nothing
        if (!this.needsConsentValue || !document.querySelector(CONSENT_BANNER_SELECTOR)) {
            this.schedule();

            return;
        }

        if (window.CookieConsent?.acceptedCategory("content")) {
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

    listen() {
        window.addEventListener("cc:onConsent", this.onConsent);
        window.addEventListener("cc:onChange", this.onConsent);
    }

    stopListening() {
        window.removeEventListener("cc:onConsent", this.onConsent);
        window.removeEventListener("cc:onChange", this.onConsent);
    }

    accept() {
        // The banner element can be in the page before its own script has set this global - a click in that window is a no-op rather than a thrown error
        window.CookieConsent?.acceptCategory("content");
    }

    onConsent() {
        if (window.CookieConsent?.acceptedCategory("content")) {
            this.consentTarget.hidden = true;
            this.schedule();
        }
    }

    // "cc:onConsent" fires on every load once the choice is known, so a returning visitor gets the map without clicking again - and a map already drawn is not drawn a second time
    schedule() {
        this.stopListening();

        if (this.map) {
            return;
        }

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

    // A failure of any kind leaves the canvas hidden and the list of places on screen, which is the whole point of rendering that list server-side: a tile server refusing the request, a key Google turned down, a stylesheet that never arrived
    async draw() {
        // Revealed before the library is handed the element, never after: both providers read the container's size as they build, and a display:none box is 0x0 - the map then paints a grey grid that only puts itself right on the next window resize
        this.canvasTarget.hidden = false;

        try {
            this.map = "google" === this.providerValue ? await this.drawGoogle() : await this.drawLeaflet();
        } catch {
            this.canvasTarget.hidden = true;
            await this.explain();
        }
    }

    // The two hosts a Google map is fetched from: a violation raised by anything else on the page - a font, another embed - is not this block's to report
    onViolation(event) {
        if (event.blockedURI?.includes("maps.googleapis.com") || event.blockedURI?.includes("maps.gstatic.com")) {
            this.refused.push(event.effectiveDirective || event.violatedDirective);
        }
    }

    // Why the map is not there, in place, for whoever may do something about it - a visitor is shown the list of places and told nothing about a setting they cannot change
    // Only what stopped the library from loading at all: a map that draws over a policy refusing its tiles is a grey grid nothing here can see, and that one is the health check's to raise (see MapCspHealthCheckProvider)
    async explain() {
        if (!this.hasDiagnosticTarget) {
            return;
        }

        // The violation is queued rather than raised in front of the failing load, so it is read a tick later
        await new Promise((resolve) => setTimeout(resolve, 0));

        this.diagnosticTarget.textContent = this.refused.length > 0
            ? this.diagnosticCspValue.replace("%directive%", this.refused[0])
            : this.diagnosticValue;
        this.diagnosticTarget.hidden = false;
    }

    async drawLeaflet() {
        const L = await this.loadLeaflet();

        const map = L.map(this.canvasTarget).setView([this.pointsValue[0].latitude, this.pointsValue[0].longitude], this.zoomValue);
        L.tileLayer(this.tileUrlValue, { attribution: this.attributionValue }).addTo(map);

        // A divIcon and not Leaflet's default marker: the pin is drawn by sass/_map.scss, so it takes --ui-map-pin-color and follows the site's own palette rather than staying the library's blue. It also spares the two images only L.Icon.Default asks for, which the stylesheet never names and nothing therefore vendors (see config/vendor-assets.json)
        const icon = L.divIcon({ className: "ui-map__pin", iconSize: [24, 24], iconAnchor: [12, 24], popupAnchor: [0, -24] });
        const markers = this.pointsValue.map((point) => L
            .marker([point.latitude, point.longitude], { icon, title: point.label })
            .bindPopup(this.popup(point))
            .addTo(map));

        this.frame(markers.length, () => map.fitBounds(L.featureGroup(markers).getBounds(), { padding: [BOUNDS_PADDING, BOUNDS_PADDING] }));

        return map;
    }

    async drawGoogle() {
        const google = await this.loadGoogle();
        const first = this.pointsValue[0];
        const map = new google.maps.Map(this.canvasTarget, {
            center: { lat: Number(first.latitude), lng: Number(first.longitude) },
            zoom: this.zoomValue,
            mapTypeControl: false,
            streetViewControl: false,
        });

        // One window reused by every marker: Google keeps each one it is given open, and a map of ten places would end up with ten popups stacked over it
        const infoWindow = new google.maps.InfoWindow();
        const bounds = new google.maps.LatLngBounds();

        for (const point of this.pointsValue) {
            const position = { lat: Number(point.latitude), lng: Number(point.longitude) };
            const marker = new google.maps.Marker({ position, map, title: point.label });
            marker.addListener("click", () => {
                infoWindow.setContent(this.popup(point));
                infoWindow.open(map, marker);
            });
            bounds.extend(position);
        }

        this.frame(this.pointsValue.length, () => map.fitBounds(bounds, BOUNDS_PADDING));

        return map;
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

    // Served from this bundle and not from a package the app has to require, nor from a CDN: a consuming site gets a working map on the bundle's own update, and a third party never receives the visitor's ip. Loaded here rather than through the front stylesheet list so it only costs the pages actually carrying a map
    // The stylesheet is waited for, where cookie-consent.js does not wait for its own: Leaflet reads the sizes its own rules give the panes as it builds, and a map built before they apply lays its tiles out against an unstyled box
    loadLeaflet() {
        if (leafletLoader) {
            return leafletLoader;
        }

        leafletLoader = Promise.all([
            this.load("link", { rel: "stylesheet", href: this.stylesheetValue }),
            this.load("script", { src: this.scriptValue }),
        ]).then(() => window.L).catch((error) => {
            // Cleared so a later map may try again - a single failed fetch must not leave every map on the site refusing to draw for the rest of the visit
            leafletLoader = null;

            throw error;
        });

        return leafletLoader;
    }

    // One element, appended to the head, resolved on its own load event - a failure rejects, which draw() answers by leaving the list of places on screen
    load(tag, attributes) {
        return new Promise((resolve, reject) => {
            const element = Object.assign(document.createElement(tag), attributes);
            element.onload = resolve;
            element.onerror = () => reject(new Error(`${tag} could not be loaded`));
            document.head.append(element);
        });
    }

    // The page's own nonce, and an empty <style> carrying it for Google to read the style one from
    // It takes the nonce of the *first* element of each kind it finds, script for the scripts it injects and style for the styles, so a page with no <style nonce> at all - which is every page until Turbo renders its progress bar - would leave the styles half unnonced. Answers "" where the site serves no policy, and nothing is appended then
    carryNonce() {
        const nonce = document.querySelector('meta[name="csp-nonce"]')?.content ?? "";

        if ("" !== nonce && !document.querySelector("style[data-ui-map-nonce]")) {
            const carrier = document.createElement("style");
            carrier.nonce = nonce;
            carrier.dataset.uiMapNonce = "";
            document.head.append(carrier);
        }

        return nonce;
    }

    // Appended once per document, whatever the number of maps on the page - Google's loader refuses a second copy of itself and logs over the whole page when it gets one
    loadGoogle() {
        if (googleLoader) {
            return googleLoader;
        }

        googleLoader = new Promise((resolve, reject) => {
            if (window.google?.maps) {
                resolve(window.google);

                return;
            }

            window[GOOGLE_CALLBACK] = () => resolve(window.google);

            const script = document.createElement("script");
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(this.apiKeyValue)}&loading=async&callback=${GOOGLE_CALLBACK}`;
            script.async = true;
            // Google copies this onto the <style> and <script> elements its API injects afterwards (see its Content-Security-Policy guide), which is what lets a site keep a nonced "style-src" rather than opening it to inline styles for the sake of a map
            script.nonce = this.carryNonce();
            // A key Google refuses, a Content-Security-Policy that does not allow its host: the promise is rejected so draw() keeps the list of places on screen, and it is cleared so a later map may try again
            script.onerror = () => {
                googleLoader = null;
                reject(new Error("Google Maps could not be loaded"));
            };
            document.head.append(script);
        });

        return googleLoader;
    }
}
