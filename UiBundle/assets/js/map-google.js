/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
// Google Maps' side of the map (see assets/js/map.js and Map/GoogleMapsProvider): its JavaScript API, from the browser key the provider hands the page, drawn once the visitor has accepted third-party content
import { BOUNDS_PADDING, PIN_SIZE } from "./map.js";

// Google's loader takes a global callback, so the script is appended once per document (see map.js, which keeps the promise) and every controller on the page waits on it
const GOOGLE_CALLBACK = "__c975lUiMapGoogleReady";

export default {
    // The two hosts a Google map is fetched from: a violation raised by anything else on the page - a font, another embed - is not this block's to report
    blocks(uri) {
        return Boolean(uri?.includes("maps.googleapis.com") || uri?.includes("maps.gstatic.com"));
    },

    // Appended once per document, whatever the number of maps on the page - Google's loader refuses a second copy of itself and logs over the whole page when it gets one
    load(settings) {
        return new Promise((resolve, reject) => {
            if (window.google?.maps) {
                resolve(window.google);

                return;
            }

            window[GOOGLE_CALLBACK] = () => resolve(window.google);

            const script = document.createElement("script");
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(settings.apiKey)}&loading=async&callback=${GOOGLE_CALLBACK}`;
            script.async = true;
            // Google copies this onto the <style> and <script> elements its API injects afterwards (see its Content-Security-Policy guide), which is what lets a site keep a nonced "style-src" rather than opening it to inline styles for the sake of a map
            script.nonce = carryNonce();
            // A key Google refuses, a Content-Security-Policy that does not allow its host: the promise is rejected so the controller keeps the list of places on screen
            script.onerror = () => reject(new Error("Google Maps could not be loaded"));
            document.head.append(script);
        });
    },

    draw(c, google) {
        const first = c.pointsValue[0];
        const map = new google.maps.Map(c.canvasTarget, {
            center: { lat: Number(first.latitude), lng: Number(first.longitude) },
            zoom: c.zoomValue,
            mapTypeControl: false,
            streetViewControl: false,
        });

        // One window reused by every marker: Google keeps each one it is given open, and a map of ten places would end up with ten popups stacked over it
        const infoWindow = new google.maps.InfoWindow();
        const bounds = new google.maps.LatLngBounds();
        const markers = [];

        for (const point of c.pointsValue) {
            const position = { lat: Number(point.latitude), lng: Number(point.longitude) };
            // Same contract as the Leaflet driver: a point's own image where it names one, Google's default marker otherwise
            const marker = new google.maps.Marker({
                position,
                map,
                title: point.label,
                ...(point.icon ? { icon: { url: point.icon, scaledSize: new google.maps.Size(PIN_SIZE, PIN_SIZE) } } : {}),
            });
            marker.addListener("click", () => {
                infoWindow.setContent(c.popup(point));
                infoWindow.open(map, marker);
            });
            markers.push(marker);
            bounds.extend(position);
        }

        const fit = () => map.fitBounds(bounds, BOUNDS_PADDING);
        c.frame(c.pointsValue.length, fit);

        // Same contract as the Leaflet driver: the view the map opened on, and the window the place before it left open closed with it
        c.reframe = () => {
            infoWindow.close();
            c.frame(c.pointsValue.length, fit);
        };

        // Same contract as the Leaflet driver: the view is brought onto the marker, never taken further away than it already was, and its own popup is opened
        c.locator = (index) => {
            const marker = markers[index];
            map.setCenter(marker.getPosition());
            map.setZoom(Math.max(map.getZoom(), c.zoomValue));
            infoWindow.setContent(c.popup(c.pointsValue[index]));
            infoWindow.open(map, marker);
        };
        c.locateEarlyPick();

        return map;
    }
};

// The page's own nonce, and an empty <style> carrying it for Google to read the style one from
// It takes the nonce of the *first* element of each kind it finds, script for the scripts it injects and style for the styles, so a page with no <style nonce> at all - which is every page until Turbo renders its progress bar - would leave the styles half unnonced. Answers "" where the site serves no policy, and nothing is appended then
function carryNonce() {
    const nonce = document.querySelector('meta[name="csp-nonce"]')?.content ?? "";

    if ("" !== nonce && !document.querySelector("style[data-ui-map-nonce]")) {
        const carrier = document.createElement("style");
        carrier.nonce = nonce;
        carrier.dataset.uiMapNonce = "";
        document.head.append(carrier);
    }

    return nonce;
}
