/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// The gesture layer every sortable in the back-office is built on, in Pointer Events - mouse, finger and stylus in one set of events. Native HTML5 drag and drop, which this replaces, is a dead end at the finger: no touch browser can be counted on to emit "dragstart" at all, and the "draggable" attribute those sortables used to arm on "mousedown" was never even set in time, a touch emitting its emulated mouse events only once the gesture is already over.
// What it owns is the gesture and nothing else: where a dragged element should land, and what to do once it has, stay entirely with the caller (see ea-sortable.js) - the shapes differ too much between a vertical list of rows, a wrapping grid of thumbnails and a table.

// Pixels the pointer has to travel before the gesture counts as a drag rather than a click
const DRAG_THRESHOLD = 5;

// Distance to the top/bottom of the viewport within which the page scrolls itself, and how fast it does at the very edge
const SCROLL_EDGE = 60;
const SCROLL_STEP = 14;

// One gesture at a time, module-scoped rather than per-zone: the move/up listeners live on the document, and a second finger landing on another handle must not start a competing drag
let active = null;

// Turns an element into a place a drag can be started from. Nothing happens until the pointer has actually travelled DRAG_THRESHOLD pixels, so a plain click or tap on the zone keeps doing whatever it did before.
// Options: "item" is the element to drag (or a function receiving the pointerdown event and returning it), "touch" set to false restricts the zone to the mouse, "ignore" is a selector a pointerdown inside of never starts a drag from, and "onStart"/"onMove"/"onDrop"/"onCancel" are the caller's four hooks - none of them ever fires for a gesture that stayed under the threshold.
export function addSortGesture(zone, options) {
    // Without this the browser claims the gesture for panning as soon as a finger moves and no pointermove ever reaches us. Only on zones that accept touch: one covering a whole row header would otherwise make the page unscrollable from everywhere that header sits.
    if (false !== options.touch) zone.classList.add('ui-sort-zone-touch');

    zone.addEventListener('pointerdown', event => onPointerDown(event, options));
}

// Arms the gesture without committing to it yet - the drag itself only begins once the pointer moves (see onPointerMove)
function onPointerDown(event, options) {
    if (active || 0 !== event.button) return;
    if (false === options.touch && 'mouse' !== event.pointerType) return;
    if (options.ignore && event.target.closest(options.ignore)) return;

    const item = 'function' === typeof options.item ? options.item(event) : options.item;
    if (!item) return;

    active = {
        options,
        item,
        pointerId: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        x: event.clientX,
        y: event.clientY,
        started: false,
        frame: null,
    };

    // On the document rather than through setPointerCapture() on the zone: reordering reparents the dragged element, and a pointer captured by an element being moved in the DOM loses that capture mid-gesture. Touch keeps its own implicit capture, and every event bubbles here either way.
    document.addEventListener('pointermove', onPointerMove);
    document.addEventListener('pointerup', onPointerUp);
    document.addEventListener('pointercancel', onPointerCancel);
}

function onPointerMove(event) {
    if (!active || event.pointerId !== active.pointerId) return;

    active.x = event.clientX;
    active.y = event.clientY;

    if (!active.started) {
        if (Math.abs(active.x - active.startX) < DRAG_THRESHOLD && Math.abs(active.y - active.startY) < DRAG_THRESHOLD) return;
        begin();
    }

    active.options.onMove?.(active.item, active.x, active.y);
}

// The point of no return: past here the gesture is a drag and no longer a click
function begin() {
    active.started = true;
    active.item.classList.add('ui-dragging');

    // Taken out of hit-testing the way a native drag image was: a caller asking what sits under the pointer would otherwise always be answered the dragged element itself, or a collection nested inside it, instead of what it is being dropped onto
    document.body.classList.add('ui-dragging-body');

    active.options.onStart?.(active.item);
    active.frame = requestAnimationFrame(autoScroll);
}

// Native drag and drop scrolled the page on its own near the viewport edges; a pointer drag has to do it itself, or a collection taller than the screen can only ever be reordered as far as the screen shows - on a phone, that is most of them
function autoScroll() {
    if (!active) return;

    const above = active.y - SCROLL_EDGE;
    const below = active.y - (window.innerHeight - SCROLL_EDGE);
    let delta = 0;

    if (above < 0) delta = Math.max(above / SCROLL_EDGE, -1) * SCROLL_STEP;
    else if (below > 0) delta = Math.min(below / SCROLL_EDGE, 1) * SCROLL_STEP;

    // Replayed at the unchanged pointer position: the rows just moved under it, so the landing spot is no longer the one computed before the scroll
    if (0 !== delta) {
        window.scrollBy(0, delta);
        active.options.onMove?.(active.item, active.x, active.y);
    }

    active.frame = requestAnimationFrame(autoScroll);
}

function onPointerUp(event) {
    if (!active || event.pointerId !== active.pointerId) return;
    finish(false);
}

function onPointerCancel(event) {
    if (!active || event.pointerId !== active.pointerId) return;
    finish(true);
}

// A gesture that never passed the threshold ends silently: it was a click, and the caller is told nothing about it
function finish(cancelled) {
    const { options, item, started, frame } = active;

    if (null !== frame) cancelAnimationFrame(frame);
    document.removeEventListener('pointermove', onPointerMove);
    document.removeEventListener('pointerup', onPointerUp);
    document.removeEventListener('pointercancel', onPointerCancel);
    active = null;

    if (!started) return;

    item.classList.remove('ui-dragging');
    document.body.classList.remove('ui-dragging-body');
    suppressNextClick();

    if (cancelled) options.onCancel?.(item);
    else options.onDrop?.(item);
}

// A drag started on something clickable - a button, an accordion header - must not also fire that click on release, the way the native drag never did.
// The listener gives up on the next pointerdown as well as on the click it eats, because a pointer that moved between down and up produces no click at all in most browsers: one waiting only for a click would sit there and swallow the user's next real one instead.
function suppressNextClick() {
    const stop = () => {
        document.removeEventListener('click', swallow, true);
        document.removeEventListener('pointerdown', stop, true);
    };
    const swallow = event => {
        event.preventDefault();
        event.stopPropagation();
        stop();
    };

    document.addEventListener('click', swallow, true);
    document.addEventListener('pointerdown', stop, true);
}
