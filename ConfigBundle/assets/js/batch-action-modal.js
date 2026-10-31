/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// EasyAdmin's batch modal says every action "cannot be undone": that line only stays for a danger one, the move to the recycle bin being a warning (see EasyAdminActionHelper::trashBatchDelete()). A plain module delegating from the document, as the batch buttons are EasyAdmin's own
document.addEventListener('click', (event) => {
    const action = event.target.closest?.('[data-action-batch]');
    const content = document.querySelector('#batch-action-confirmation-message + p');
    if (action && content) {
        content.classList.toggle('d-none', 'danger' !== (action.dataset.actionVariant || 'danger'));
    }
});
