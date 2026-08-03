/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// A site with a nonce-based style-src CSP makes 'unsafe-inline' a no-op (CSP2+ behavior) - and a nonce can only ever authorize a <style>/<link> *element*, never an inline style *attribute* set from JS (.style.xxx = value, .style.setProperty(), .setAttribute('style', ...)). Any controller that needs to apply a continuous, JS-computed value (a measured height, a CSS custom property) has to go through a real <style> element instead. ES modules never populate document.currentScript (the usual way to read "my own" nonce), so this copies the nonce off any nonce'd element already present in the document - there always is one whenever the page's CSP requires a nonce at all (e.g. importmap()'s own <script type="importmap" nonce="...">).
export function createNoncedStyleElement() {
    const style = document.createElement('style');
    const nonce = document.querySelector('[nonce]')?.nonce;
    if (nonce) {
        style.nonce = nonce;
    }
    document.head.appendChild(style);

    return style;
}
