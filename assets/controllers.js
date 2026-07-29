import { startStimulusApp } from '@symfony/stimulus-bundle';
import AnimateScrollController from './js/animate-scroll.js';
import MenuController from './js/menu.js';

// Front-end controllers, used on public pages Loaded as its own <script type="module"> tag (see importmap.php), starts its own Stimulus app
const app = startStimulusApp();
app.register('animateScroll', AnimateScrollController);
app.register('menu', MenuController);

// Dynamic import() so AssetMapper marks these lazy: an importmap entry, but no <link rel="modulepreload">
// Keys are the Stimulus identifiers as registered, matching what the templates write in data-controller
const LAZY_CONTROLLERS = {
    blockEditOverlay: () => import('./js/block-edit-overlay.js'),
    captcha: () => import('./js/captcha.js'),
    confetti: () => import('./js/confetti.js'),
    imageCompare: () => import('./js/image-compare.js'),
    password: () => import('./js/password.js'),
    slider: () => import('./js/slider.js'),
    videoIframe: () => import('./js/video-iframe.js'),
};

const registered = new Set();

// Registers only the lazy controllers this document actually contains. Stimulus connects a controller as soon as it is registered, so a late registration still picks up elements already in the DOM - there is no race to lose here.
function registerPresentControllers() {
    for (const [identifier, load] of Object.entries(LAZY_CONTROLLERS)) {
        if (registered.has(identifier) || !document.querySelector(`[data-controller~="${identifier}"]`)) {
            continue;
        }

        registered.add(identifier);
        load().then((module) => app.register(identifier, module.default));
    }
}

registerPresentControllers();

// Turbo swaps the <body> without re-running this module, so a page reached by navigation would otherwise never get its own lazy controllers - a slider on page 2 would simply never start after landing on page 1 first
document.addEventListener('turbo:load', registerPresentControllers);
