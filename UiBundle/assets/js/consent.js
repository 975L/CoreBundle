/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// The one place an embed (map.js, video-iframe.js) asks about consent, whatever banner the site runs: the banner is a connector, vanilla-cookieconsent v3 (cookie-consent.js) the one shipped. Another banner sets window.c975lConsentAdapter, read on every call, with what differs: present(), ready(), accepted(category), accept(category) and the names of the window events it fires when the choice is known or changes

// Whoever provides the banner chooses the Stimulus identifier, so both spellings are matched: a missed match is not a harmless no-op, the embed concluding the page has no banner and loading before any consent has been given
const BANNER_SELECTOR = '[data-controller~="cookie-consent"], [data-controller~="cookieConsent"]';

// vanilla-cookieconsent v3 (https://cookieconsent.orestbida.com/): its window.CookieConsent global and its cc:onConsent/cc:onChange events, the first one fired on every load once the choice is known
const VANILLA = {
    present: () => null !== document.querySelector(BANNER_SELECTOR),
    ready: () => Boolean(window.CookieConsent),
    accepted: (category) => Boolean(window.CookieConsent?.acceptedCategory(category)),
    // A single category given to acceptCategory() replaces the accepted set, so the ones already accepted are passed along; before the banner's script has set its global, a click is a no-op rather than a thrown error
    accept: (category) => {
        const accepted = window.CookieConsent?.getUserPreferences().acceptedCategories ?? [];
        window.CookieConsent?.acceptCategory([...accepted, category]);
    },
    events: ["cc:onConsent", "cc:onChange"],
};

const adapter = () => ({ ...VANILLA, ...(window.c975lConsentAdapter ?? {}) });

// A page with no banner still asks before loading a third party: the accord is kept for the visit in sessionStorage, in memory only where storage is refused, and told through this event
const LOCAL_EVENT = "c975l:consent";
const localAccords = new Set();
const storageKey = (category) => `c975l-consent-${category}`;

// The accord given on a page with no banner, read back from the visit's storage
function localAccepted(category) {
    try {
        return "1" === window.sessionStorage.getItem(storageKey(category));
    } catch {
        return localAccords.has(category);
    }
}

// Kept for the visit, then told to whoever waits on it
function localAccept(category) {
    try {
        window.sessionStorage.setItem(storageKey(category), "1");
    } catch {
        // Storage refused (private window, blocked site data): the accord lasts as long as the page
        localAccords.add(category);
    }
    window.dispatchEvent(new Event(LOCAL_EVENT));
}

// Whether the page carries a banner at all: without one the accord is asked in place and kept for the visit (see localAccept)
export function hasBanner() {
    return adapter().present();
}

// Whether the banner's script is there to answer, so a missing answer is told apart from a refusal
export function isReady() {
    return !hasBanner() || adapter().ready();
}

export function isAccepted(category) {
    return hasBanner() ? adapter().accepted(category) : localAccepted(category);
}

export function accept(category) {
    if (!hasBanner()) {
        localAccept(category);

        return;
    }

    adapter().accept(category);
}

// Calls back whenever the choice is known or changes, answering the function that stops it. The same bound callback given twice is attached once, as addEventListener does
export function onChange(callback) {
    const events = [...adapter().events, LOCAL_EVENT];
    for (const event of events) {
        window.addEventListener(event, callback);
    }

    return () => {
        for (const event of events) {
            window.removeEventListener(event, callback);
        }
    };
}
