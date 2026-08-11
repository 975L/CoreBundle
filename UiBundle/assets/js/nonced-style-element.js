/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// A site with a nonce-based style-src CSP makes 'unsafe-inline' a no-op (CSP2+ behavior) - and a nonce can only ever authorize a <style>/<link> *element*, never an inline style *attribute* set from JS (.style.xxx = value, .style.setProperty(), .setAttribute('style', ...)). Any controller that needs to apply a continuous, JS-computed value (a measured height, a CSS custom property) has to go through a real <style> element instead. ES modules never populate document.currentScript (the usual way to read "my own" nonce), so this reads the nonce off the document itself.
// A wrong value is not a wrong style but no style at all: a rejected element exposes a null "sheet", which every caller has to be ready for (see block-edit-overlay.js). Which value is the right one is what documentNonce() answers below.
export function createNoncedStyleElement() {
    const style = document.createElement('style');
    const nonce = documentNonce();
    if (nonce) {
        style.nonce = nonce;
    }
    document.head.appendChild(style);

    return style;
}

// The nonce of the document itself, the only one its CSP ever authorizes: the browser enforces the header of the response the document was built from, and Turbo never builds a new one - it swaps the <body> and leaves the <meta> the layout wrote in place, where every element the new body brings in carries the *new* response's nonce instead. Reading one of those is what left the button of an editor unplaced from the first navigation on: the <style> was rejected, its coordinates with it. In dev the debug toolbar's own stylesheet <link> is the very first carrier a query finds, so it happens on every page; in production a page composing a Banner or a FlipCard renders a <style> of its own that does the same.
// The layout is what makes this value the right one: it asks for a nonce on both directives (see layout.html.twig), and NelmioSecurityBundle hands back the same string for a given response - so what the meta carries is authorized by style-src as much as by script-src.
// The element lookup stays for a layout writing no meta at all, style-src carriers first (a stylesheet <link>, an earlier <style>) and the plain [nonce] last: that one is importmap()'s <script>, and a site nonce-ing script-src alone leaves style-src on 'unsafe-inline', where a wrong value is ignored rather than enforced.
function documentNonce() {
    const meta = document.querySelector('meta[name="csp-nonce"]');
    if (meta?.content) {
        return meta.content;
    }

    const carrier =
        document.querySelector('link[rel~="stylesheet"][nonce], style[nonce]') ??
        document.querySelector('[nonce]');

    return carrier?.nonce;
}
