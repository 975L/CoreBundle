/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// A Bootstrap modal built on the fly to tell the user something went wrong, then removed from the DOM again - what
// replaces window.alert() in the back-office, which blocks the whole tab and looks nothing like the rest of it.
// EasyAdmin's own "#modal-action-confirmation" is deliberately not reused, unlike title-confirm.js does for an actual
// confirmation: its button keeps whatever handler the last opened action attached to it (see its app.js, the listener
// is only dropped once the button is actually clicked), so a click meant to dismiss a message could run a delete.
// window.bootstrap is exposed globally by EasyAdmin's own admin.js; without it, the native alert is still better than
// a message nobody sees.
export function showAdminMessage(title, message = '', closeLabel = 'OK') {
    if (!window.bootstrap) {
        window.alert([title, message].filter(Boolean).join('\n'));
        return;
    }

    const modal = buildModal(title, message, closeLabel);
    document.body.append(modal);

    // Single-use: the next message builds its own rather than reusing an element the DOM has to keep around
    modal.addEventListener('hidden.bs.modal', () => { modal.remove(); }, { once: true });

    window.bootstrap.Modal.getOrCreateInstance(modal).show();
}

// Built element by element rather than from an HTML string: every value here is a translated label, i.e. text, and textContent is what keeps it that way
function buildModal(title, message, closeLabel) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.tabIndex = -1;

    const dialog = document.createElement('div');
    dialog.className = 'modal-dialog modal-dialog-centered';

    const content = document.createElement('div');
    content.className = 'modal-content';

    const body = document.createElement('div');
    body.className = 'modal-body';

    const heading = document.createElement('h4');
    heading.textContent = title;
    body.append(heading);

    if (message) {
        const paragraph = document.createElement('p');
        paragraph.className = 'mb-0';
        paragraph.textContent = message;
        body.append(paragraph);
    }

    const footer = document.createElement('div');
    footer.className = 'modal-footer';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-secondary';
    button.setAttribute('data-bs-dismiss', 'modal');
    button.textContent = closeLabel;
    footer.append(button);

    content.append(body, footer);
    dialog.append(content);
    modal.append(dialog);

    return modal;
}
