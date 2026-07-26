/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// icon_picker_theme.html.twig only renders the ".ui-icon-picker" markup, not this behavior: a CollectionType (e.g. SocialLinksType) starting empty renders no widget at all on first load, and EasyAdmin's field-collection.js then has to eval() the widget's own inline <script> to set it up on the row it just cloned - which a CSP without 'unsafe-eval' silently blocks. Loading this setup once as a real module sidesteps both: it runs regardless of how many pickers exist yet, and the delegated listeners below work for any ".ui-icon-picker" added to the DOM afterwards. Styling lives in sass/management/_icon-picker.scss, not injected here - a CSP with a nonce on style-src blocks any <style> created via document.createElement, unsafe-inline or not.

function render(picker, query) {
    const icons = JSON.parse(picker.dataset.icons || '[]');
    const grid = picker.querySelector('.ui-icon-grid');
    const q = query.toLowerCase().trim();
    const filtered = q ? icons.filter(i => i.name.includes(q)) : icons;

    if (!filtered.length) { grid.hidden = true; return; }

    grid.innerHTML = '';
    filtered.forEach(i => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ui-icon-item';
        btn.dataset.path = i.path;
        btn.title = i.name;
        const img = document.createElement('img');
        img.src = i.url;
        img.width = 24;
        img.height = 24;
        img.loading = 'lazy';
        // Decorative: the <span> appended right after it already names the icon
        img.alt = '';
        img.setAttribute('aria-hidden', 'true');
        const span = document.createElement('span');
        span.textContent = i.name;
        btn.appendChild(img);
        btn.appendChild(span);
        grid.appendChild(btn);
    });
    grid.hidden = false;
}

document.addEventListener('input', event => {
    if (!event.target.classList.contains('ui-icon-search')) return;
    const picker = event.target.closest('.ui-icon-picker');
    if (!event.target.value) {
        const hidden = picker.querySelector('input[type="hidden"]');
        hidden.value = '';
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
        picker.querySelector('.ui-icon-preview').hidden = true;
    }
    render(picker, event.target.value);
});

document.addEventListener('focusin', event => {
    if (!event.target.classList.contains('ui-icon-search')) return;
    if (!event.target.value) render(event.target.closest('.ui-icon-picker'), '');
});

document.addEventListener('click', event => {
    const item = event.target.closest('.ui-icon-item');
    if (item) {
        const picker = item.closest('.ui-icon-picker');
        const hidden = picker.querySelector('input[type="hidden"]');
        // "name" mode (data-value-field, see IconPickerType's "value_field" option) stores the bare icon key instead of its asset path - the preview always uses the actual image regardless
        hidden.value = picker.dataset.valueField === 'name' ? item.title : item.dataset.path;
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
        picker.querySelector('.ui-icon-search').value = item.title;
        picker.querySelector('.ui-icon-grid').hidden = true;
        const preview = picker.querySelector('.ui-icon-preview');
        preview.src = item.querySelector('img').src;
        preview.hidden = false;
        return;
    }
    if (!event.target.classList.contains('ui-icon-search')) {
        document.querySelectorAll('.ui-icon-grid').forEach(g => { g.hidden = true; });
    }
});
