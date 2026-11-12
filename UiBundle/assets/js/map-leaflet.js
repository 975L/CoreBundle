/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
// Leaflet's side of the map (see assets/js/map.js and Map/LeafletProvider): OpenStreetMap's tiles, drawn by this bundle's own copy of the library, which needs no consent
import { BOUNDS_PADDING, PIN_SIZE, load } from "./map.js";

// A point's own image, anchored the way the pin is: on its tip, with the popup opening above it
const ICON = (L, url) => L.icon({
    iconUrl: url,
    iconSize: [PIN_SIZE, PIN_SIZE],
    iconAnchor: [PIN_SIZE / 2, PIN_SIZE],
    popupAnchor: [0, -PIN_SIZE],
});

export default {
    // None to report: the library comes from this very site
    blocks() {
        return false;
    },

    // Served from this bundle and not from a package the app has to require, nor from a CDN: a consuming site gets a working map on the bundle's own update, and a third party never receives the visitor's ip. The stylesheet is waited for, where cookie-consent.js does not wait for its own: Leaflet reads the sizes its own rules give the panes as it builds, and a map built before they apply lays its tiles out against an unstyled box
    load(settings) {
        return Promise.all([
            load("link", { rel: "stylesheet", href: settings.stylesheet }),
            load("script", { src: settings.script }),
        ]).then(() => window.L);
    },

    draw(c, L) {
        const map = L.map(c.canvasTarget).setView([c.pointsValue[0].latitude, c.pointsValue[0].longitude], c.zoomValue);
        L.tileLayer(c.settingsValue.tileUrl, { attribution: c.settingsValue.attribution }).addTo(map);

        // A divIcon and not Leaflet's default marker: the pin is drawn by sass/_map.scss, so it takes --ui-map-pin-color and follows the site's own palette rather than staying the library's blue. It also spares the two images only L.Icon.Default asks for, which the stylesheet never names and nothing therefore vendors (see config/vendor-assets.json)
        const pin = L.divIcon({ className: "ui-map__pin", iconSize: [PIN_SIZE, PIN_SIZE], iconAnchor: [PIN_SIZE / 2, PIN_SIZE], popupAnchor: [0, -PIN_SIZE] });

        // A point naming its own image is drawn with it, at the pin's size and anchored the same way, so a map mixing sorts of places reads as such; every other point keeps the pin above, shared by all of them
        const markers = c.pointsValue.map((point) => L
            .marker([point.latitude, point.longitude], { icon: point.icon ? ICON(L, point.icon) : pin, title: point.label })
            .bindPopup(c.popup(point))
            .addTo(map));

        // Read once and not per fit: a group is what every marker reports its events to, and one built again on each reframe would leave each of them with a parent more to walk
        const bounds = L.featureGroup(markers).getBounds();
        const fit = (animate) => map.fitBounds(bounds, { padding: [BOUNDS_PADDING, BOUNDS_PADDING], animate });
        // The opening view is never animated: a zoom still running when the page is left answers its transitionend on panes Leaflet already took down, an uncaught error
        c.frame(markers.length, () => fit(false));

        // The view the map opened on, framed on every marker again and the open popup closed - what the picker's first option goes back to, animated since the visitor asked for it
        c.reframe = () => {
            map.closePopup();
            c.frame(markers.length, () => fit(true));
        };

        // Never zoomed back out: a map framed on the whole listing is closer than the block's own zoom, and a place picked from the menu would otherwise take the view further away than it already was
        c.locator = (index) => {
            const marker = markers[index];
            map.setView(marker.getLatLng(), Math.max(map.getZoom(), c.zoomValue));
            marker.openPopup();
        };
        c.locateEarlyPick();

        return map;
    }
};
