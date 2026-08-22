/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// Where this browser keeps whose list it is holding, and what is in it. localStorage rather than a cookie, and its own store rather than the rating one: the token has no business travelling in a header on every request, a Set-Cookie would be answered on pages that are cached and shared between visitors, and a visitor clearing one of the two features must not lose the other
const STORE = "c975l-favorite";

// A browser refusing storage (private mode, site data blocked) reads as an empty list rather than taking the page down with it
export function read() {
    try {
        const stored = JSON.parse(window.localStorage.getItem(STORE) || "{}");

        return { token: stored.token || null, keys: stored.keys || {} };
    } catch {
        return { token: null, keys: {} };
    }
}

export function write(store) {
    try {
        window.localStorage.setItem(STORE, JSON.stringify(store));
    } catch {
        // Nothing to do: the list is the server's, and this browser simply will not remember it between two pages
    }
}

// Minted on the first click and never before, which is what keeps this out of consent territory: no identifier is created for someone who merely reads the page
export function newToken() {
    return window.crypto.randomUUID().replaceAll("-", "");
}

// What the server just answered, written over what this browser assumed: a visitor signing in on a new device holds a list their storage knows nothing about
export function sync(keys) {
    const store = read();
    store.keys = {};
    keys.forEach((key) => {
        store.keys[key] = true;
    });
    write(store);

    return store;
}
