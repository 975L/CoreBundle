import { startStimulusApp } from '@symfony/stimulus-bundle';
import AnimateScrollController from './js/animate-scroll.js';
import MenuController from './js/menu.js';

// Front-end controllers, used on public pages Loaded as its own <script type="module"> tag (see importmap.php), starts its own Stimulus app
const app = startStimulusApp();
app.register('animateScroll', AnimateScrollController);
app.register('menu', MenuController);

// Controllers only a handful of pages ever use, imported dynamically so they stay off the critical path. AssetMapper marks a dynamic import() as lazy: the module still gets an importmap entry (so it resolves at runtime) but no <link rel="modulepreload">, which is what was pulling every one of these down on every public page - a plain text page was fetching the slider, the captcha and the confetti library before painting.
// Keys are the Stimulus identifiers as registered - kebab-case for "captcha" and camelCase for the rest, matching what the templates write in data-controller (see CaptchaControllerDataAttributesTest for why that identifier can't be renamed freely).
const LAZY_CONTROLLERS = {
    blockEditOverlay: () => import('./js/block-edit-overlay.js'),
    captcha: () => import('./js/captcha.js'),
    confetti: () => import('./js/confetti.js'),
    imageCompare: () => import('./js/image-compare.js'),
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
