/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// The grip every sortable of the back-office marks its drag handle with - shared by ea-sortable.js (collection rows) and ea-index-sort.js (index rows) rather than declared twice, the two handles being the same affordance.
// No width/height here, deliberately - EasyAdmin's own icons (e.g. the delete button's) don't set them either, relying entirely on its global ".icon svg" CSS to size every icon consistently. Hard-coding a size here would make this one the odd one out instead of matching the others.
export const MOVE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" '
    + 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    + '<polyline points="5 9 2 12 5 15"/><polyline points="9 5 12 2 15 5"/>'
    + '<polyline points="15 19 12 22 9 19"/><polyline points="19 9 22 12 19 15"/>'
    + '<line x1="2" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="22"/>'
    + '</svg>';
