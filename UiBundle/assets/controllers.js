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
    // Kebab-case identifier on purpose - Stimulus derives value attribute names from the identifier as registered, so a camelCase one silently breaks every "data-cookie-consent-*-value" binding
    'cookie-consent': () => import('./js/cookie-consent.js'),
    confetti: () => import('./js/confetti.js'),
    flipCard: () => import('./js/flip-card.js'),
    heroVideo: () => import('./js/hero-video.js'),
    imageCompare: () => import('./js/image-compare.js'),
    infiniteScroll: () => import('./js/infinite-scroll.js'),
    'legal-model-edit': () => import('./js/legal-model-edit.js'),
    matomo: () => import('./js/matomo.js'),
    password: () => import('./js/password.js'),
    readmore: () => import('./js/readmore.js'),
    scrollButtons: () => import('./js/scroll-buttons.js'),
    slider: () => import('./js/slider.js'),
    toc: () => import('./js/toc.js'),
    // Kebab-case identifier, same reason as cookie-consent above: every "data-ui-rating-*-value" binding is derived from it
    'ui-rating': () => import('./js/rating.js'),
    'ui-favorite': () => import('./js/favorite.js'),
    'ui-favorite-count': () => import('./js/favorite-count.js'),
    'ui-favorites': () => import('./js/favorites.js'),
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
