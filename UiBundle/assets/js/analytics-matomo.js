/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// Matomo's side of audience measurement (see assets/js/analytics.js and Analytics/MatomoProvider): everything goes through its _paq queue, which applies the site id and the tracker url first whatever the order things were pushed in
const queue = () => (window._paq = window._paq || []);

// The url of the previous view, kept for the life of the page
let lastUrl = null;

export default {
    // Counts the page view and loads matomo.js from the instance once, the url handed already stripped of its trailing slashes. Turbo keeps the page alive across visits, so each view names its own url, title and referrer rather than the ones of the landing page
    start(settings) {
        const url = `${settings.url}/`;
        const referrer = lastUrl ?? document.referrer;
        lastUrl = window.location.href;
        queue().push(["setReferrerUrl", referrer], ["setCustomUrl", window.location.href], ["setDocumentTitle", document.title], ["trackPageView"]);

        // The tracker and its script are set up on the first visit only
        if (null !== document.querySelector(`script[src="${url}matomo.js"]`)) {
            return;
        }
        queue().push(["enableLinkTracking"], ["setTrackerUrl", `${url}matomo.php`], ["setSiteId", String(settings.id)]);

        const script = document.createElement("script");
        script.async = true;
        script.src = `${url}matomo.js`;
        document.head.appendChild(script);
    },

    // Matomo counts an order number once, so a reload records nothing twice
    trackOrder(order) {
        for (const item of order.items) {
            queue().push(["addEcommerceItem", item.sku, item.name, item.category, item.price, item.quantity]);
        }
        queue().push(["trackEcommerceOrder", order.id, order.grandTotal, order.subTotal, order.tax, order.shipping, order.discount]);
    },
};
