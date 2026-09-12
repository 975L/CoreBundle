/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Turns the neutral marks a cached fragment carries into the urls blockEditOverlay reads, mounted on the same element by the layout for editors only (see entity_edit_pattern). A fragment cached for every visitor can only say what an element is - "data-edit-entity" naming an entity of its own as "<kind>:<id>", "data-edit-field" a property of the entity around it - and never where its back-office form stands, which would serve an editor's url to anonymous visitors too. The kind is the CRUD's route path, the segment EasyAdmin names its pretty urls after: "resistant:12" opens /management/resistant/12/edit
export default class extends Controller {
    static values = { pattern: String };

    static ENTITY = "[data-edit-entity]";

    static FIELD = "[data-edit-field]";

    connect() {
        this.stamp(document.body);

        // A listing grows as the visitor scrolls: the cards it appends carry their mark and get their url too
        this.observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node instanceof Element) this.stamp(node);
                }
            }
        });
        this.observer.observe(document.body, { childList: true, subtree: true });
    }

    disconnect() {
        this.observer?.disconnect();
    }

    // An entity of its own (a card) takes that entity's form; a field (a section of a fiche) takes the form of the entity around it, opened on that very field by field-focus.js
    stamp(root) {
        this.matching(root, this.constructor.ENTITY).forEach((element) => {
            const [kind, id] = element.dataset.editEntity.split(":");
            if (kind && id) {
                element.dataset.blockEditUrl = this.patternValue.replace("__kind__", kind).replace("__id__", id);
            }
        });

        this.matching(root, this.constructor.FIELD).forEach((element) => {
            const entity = element.parentElement?.closest("[data-block-edit-url]:not([data-edit-field])");
            if (entity) {
                element.dataset.blockEditUrl = `${entity.dataset.blockEditUrl.split("?")[0]}?focusField=${encodeURIComponent(element.dataset.editField)}`;
            }
        });
    }

    // The root itself included: an appended card is the node added, not a descendant of it
    matching(root, selector) {
        return [root, ...root.querySelectorAll(selector)].filter((element) => element.matches(selector));
    }
}
