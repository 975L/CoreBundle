/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { read } from "./favorite-store.js";

// How many things are put aside, beside a menu's link to the list (SiteBundle's "favorite_link" block). Read from this browser's own store and never fetched: a navbar is part of a page served cached and shared, so a count printed in its html would be the previous visitor's
export default class extends Controller {
    static targets = ["count"];

    // Reads only, like the heart itself: nothing is written to the browser for someone who merely walks past the navbar
    connect() {
        this.paint(Object.keys(read().keys).length);
    }

    // The heart announcing it sits on a card far from here and its event bubbles to the document, hence the "@window" binding in the template
    update(event) {
        this.paint(event.detail.count);
    }

    // Hidden rather than showing a zero: an empty list has nothing to say, and the link alone reads better than "Ma liste 0"
    paint(count) {
        this.countTarget.textContent = String(count);
        this.countTarget.hidden = count < 1;
    }
}
