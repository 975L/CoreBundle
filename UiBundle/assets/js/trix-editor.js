/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// Trix has no built-in text-align support (Basecamp's own choice, repeatedly declined upstream). Its blockAttributes config has no "class" property - only "htmlAttributes" is a passthrough whitelist (already used internally for the "language" attribute of code blocks), applied both ways by html_parser.js (HTML -> model) and block_view.js (model -> HTML). A plain paragraph block has an EMPTY attribute stack, and block_view.js takes a fast render path for empty-stack blocks that ignores htmlAttributes entirely - whitelisting "class" on "default" is not enough on its own, the class would never reach the DOM. It IS still needed on "default" because html_parser.js's htmlAttributes reader always resolves a <div> to the "default" config first (plain first-match-by-tagName lookup), regardless of which named attribute actually matched. So alignment is modelled as its own named block attribute ("textAlign", tagName div, detected on parse via test()), which routes the block through the container-rendering path that DOES honor htmlAttributes. Setting the class then requires activating "textAlign" on the block BEFORE calling setHTMLAtributeAtPosition (itself a Trix-internal, not a typo we introduced - it no-ops unless the block's last attribute already whitelists "class"). This relies on non-public Trix internals (verified against basecamp/trix 2.1.19 source with a jsdom parse/render round-trip), so every touch point is feature-detected: an EasyAdmin/Trix upgrade should silently disable the alignment buttons rather than break the editor.
const ALIGN_CLASSES = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
    justify: 'text-justify',
};

const ALIGN_ATTRIBUTE = 'textAlign';

function patchTrixConfig() {
    if (typeof Trix === 'undefined' || Trix.config.blockAttributes[ALIGN_ATTRIBUTE]) {
        return;
    }
    Trix.config.blockAttributes.default.htmlAttributes = ['class'];
    Trix.config.blockAttributes[ALIGN_ATTRIBUTE] = {
        tagName: 'div',
        htmlAttributes: ['class'],
        group: false,
        test: (element) => Object.values(ALIGN_CLASSES).some((c) => element.classList.contains(c)),
    };

    const getDefaultHTML = Trix.config.toolbar.getDefaultHTML;
    Trix.config.toolbar.getDefaultHTML = function () {
        const buttons = Object.entries(ALIGN_CLASSES).map(([align, className]) => (
            `<button type="button" class="trix-button" data-align="${align}" data-align-class="${className}" title="${alignTitle(align)}" tabindex="-1">${align.charAt(0).toUpperCase()}</button>`
        )).join('');
        const group = `<span class="trix-button-group trix-button-group--text-align" data-trix-button-group="text-align">${buttons}</span>`;

        return getDefaultHTML.call(this).replace(
            '<span class="trix-button-group-spacer"></span>',
            `${group}<span class="trix-button-group-spacer"></span>`,
        );
    };
}

function alignTitle(align) {
    const titles = {
        left: 'Aligner à gauche',
        center: 'Centrer',
        right: 'Aligner à droite',
        justify: 'Justifier',
    };
    return titles[align] || align;
}

function currentBlockElement(editorElement) {
    const selection = window.getSelection();
    if (!selection.rangeCount) return null;
    const node = selection.getRangeAt(0).startContainer;
    const element = node.nodeType === Node.TEXT_NODE ? node.parentElement : node;

    // The selection is global to the document: without this check, a toolbar would align a block of another editor on the page
    if (!element || !editorElement.contains(element)) return null;

    return element.closest('div');
}

function activeAlignClass(editorElement) {
    const block = currentBlockElement(editorElement);
    return block ? Object.values(ALIGN_CLASSES).find((c) => block.classList.contains(c)) || '' : '';
}

function syncActiveButton(toolbar, activeClassName) {
    toolbar.querySelectorAll('[data-align]').forEach((button) => {
        button.classList.toggle('trix-active', button.dataset.alignClass === activeClassName);
    });
}

function initToolbarAlignment(toolbar, editorElement) {
    if (toolbar.dataset.alignInit) return;
    toolbar.dataset.alignInit = '1';

    toolbar.addEventListener('click', (event) => {
        const button = event.target.closest('[data-align]');
        if (!button) return;

        const editor = editorElement.editor;
        if (!editor || typeof editor.setHTMLAtributeAtPosition !== 'function'
            || typeof editor.activateAttribute !== 'function'
            || typeof editor.deactivateAttribute !== 'function'
            || typeof editor.attributeIsActive !== 'function') return;

        const className = button.dataset.alignClass;
        const next = activeAlignClass(editorElement) === className ? '' : className;

        if (next) {
            if (!editor.attributeIsActive(ALIGN_ATTRIBUTE)) {
                editor.activateAttribute(ALIGN_ATTRIBUTE);
            }
            editor.setHTMLAtributeAtPosition(editor.getPosition(), 'class', next);
        } else {
            editor.deactivateAttribute(ALIGN_ATTRIBUTE);
        }
        syncActiveButton(toolbar, next);
    });

    editorElement.addEventListener('trix-selection-change', () => {
        syncActiveButton(toolbar, activeAlignClass(editorElement));
    });
}

function initTrixEditors() {
    patchTrixConfig();

    document.querySelectorAll('textarea[data-trix]:not([data-trix-init])').forEach(function (textarea) {
        if (!textarea.id) return;
        textarea.dataset.trixInit = '1';
        textarea.classList.add('ea-text-editor-content', 'd-none');

        const wrapper = document.createElement('div');
        wrapper.className = 'ea-text-editor-wrapper';
        const editor = document.createElement('trix-editor');
        editor.setAttribute('input', textarea.id);
        editor.className = 'trix-content';
        wrapper.appendChild(editor);
        textarea.insertAdjacentElement('afterend', wrapper);

        editor.addEventListener('trix-initialize', () => {
            const toolbar = editor.toolbarElement;
            if (toolbar) initToolbarAlignment(toolbar, editor);
        }, { once: true });
    });
}

window.addEventListener('DOMContentLoaded', initTrixEditors);
document.addEventListener('turbo:load', initTrixEditors);
document.addEventListener('c975l:block-data-loaded', initTrixEditors);
// EasyAdmin clones the prototype and fires this instead, else the inserted trix textareas stay hidden
document.addEventListener('ea.collection.item-added', initTrixEditors);
