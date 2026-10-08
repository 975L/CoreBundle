/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// What a visitor keeps for offline use, shared by the download button (pwa-download.js), the downloads page (pwa-downloads.js) and the service worker (templates/pwa/sw.js.twig), which serves this cache first and never drops it on a deploy
export const DOWNLOADS = "c975l-pwa-downloads";

// The list of downloads lives in the same cache as their files rather than in localStorage, so a browser evicting the one evicts the other and the list never names files that are gone
const REGISTRY_URL = "/pwa-downloads.json";

// Whether the browser can keep files for offline use at all
export function isSupported() {
    return "caches" in window && "serviceWorker" in navigator;
}

// Every download, keyed as its button names it: {title, url, image, urls, size, date}
export async function readRegistry() {
    const response = await caches.match(REGISTRY_URL, { cacheName: DOWNLOADS });

    return response ? response.json() : {};
}

async function writeRegistry(registry) {
    const cache = await caches.open(DOWNLOADS);
    await cache.put(REGISTRY_URL, new Response(JSON.stringify(registry), { headers: { "Content-Type": "application/json" } }));
}

// Fetches and keeps every file of a download, reporting each one done, then records it: a file failing stops it and drops what it had kept alone
export async function download(key, entry, onProgress) {
    const cache = await caches.open(DOWNLOADS);
    const registry = await readRegistry();
    const kept = [];
    let size = 0;

    try {
        for (const url of entry.urls) {
            // Anonymous and whole: a page kept for offline use carries no session, and a stored file answers a range itself (see the worker)
            const response = await fetch(url, { credentials: "omit", cache: "reload" });
            if (!response.ok) {
                throw new Error(`${response.status} ${url}`);
            }
            size += (await response.clone().blob()).size;
            await cache.put(url, response);
            kept.push(url);
            onProgress(kept.length, entry.urls.length);
        }
    } catch (error) {
        await dropUnshared(cache, kept, registry);
        throw error;
    }

    registry[key] = { ...entry, size, date: new Date().toISOString() };
    await writeRegistry(registry);
}

// Forgets one download, its files with it unless another download still needs them - a page's scripts and styles are shared by every story
export async function remove(key) {
    const cache = await caches.open(DOWNLOADS);
    const registry = await readRegistry();
    const urls = registry[key]?.urls || [];
    delete registry[key];
    await dropUnshared(cache, urls, registry);
    await writeRegistry(registry);
}

// Forgets every download at once
export function removeAll() {
    return caches.delete(DOWNLOADS);
}

async function dropUnshared(cache, urls, registry) {
    const shared = new Set(Object.values(registry).flatMap((entry) => entry.urls));
    await Promise.all(urls.filter((url) => !shared.has(url)).map((url) => cache.delete(url)));
}
