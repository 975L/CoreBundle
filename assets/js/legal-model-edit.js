/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Gives each section of a rendered legal model its own "Edit" hover button, the way every other block already
// has one - pointing at that very section's card on the customization screen rather than at the top of a
// document holding dozens of them.
//
// Client-side on purpose: the document is cached per (block, locale) and served to every visitor alike (see
// BlockExtension), so no editor-only URL can ever be rendered into it. The block's own wrapper carries the
// single URL an editor gets (whichever bundle owns the block points a legal_model at its customization
// screen, see LegalModelEditUrl) and only exists for them - nothing happens at all for anybody else.
export default class extends Controller {
    connect() {
        const wrapper = this.element.closest("[data-block-edit-url]");
        if (!wrapper) return;

        const url = wrapper.dataset.blockEditUrl;
        const separator = url.includes("?") ? "&" : "?";

        // The identifier the models tag their units with, which is also what the customization screen keys its
        // rows on - so it is all the "focus that one" the screen needs (see legal-model.js)
        this.element.querySelectorAll("[data-legal-id]").forEach((unit) => {
            unit.dataset.blockEditUrl = url + separator + "focusUnit=" + encodeURIComponent(unit.dataset.legalId);
        });
    }
}
