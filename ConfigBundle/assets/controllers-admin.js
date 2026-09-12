import { startStimulusApp } from '@symfony/stimulus-bundle';
import ChartjsController from '@symfony/ux-chartjs';
import { Chart } from 'chart.js';
import GuidedProjectController from './js/guided-project.js';
import HealthCheckProgressController from './js/health-check-progress.js';
import HealthCheckTableController from './js/health-check-table.js';
import OnboardingTourController from './js/onboarding-tour.js';

// Guards against this module's top-level code running twice, which would add its chartjs listener twice
if (!window.__c975lConfigAdminStarted) {
    window.__c975lConfigAdminStarted = true;

    // Back-office controllers, loaded as their own module tag and joining the page's one Stimulus application - see UiBundle's controllers.js
    globalThis.c975lStimulusApp ??= startStimulusApp();
    const app = globalThis.c975lStimulusApp;
    app.register('onboarding-tour', OnboardingTourController);
    app.register('guided-project', GuidedProjectController);
    app.register('health-check-table', HealthCheckTableController);
    app.register('health-check-progress', HealthCheckProgressController);
    // render_chart() emits this exact identifier, which the shared app never gets for free from the app's bootstrap
    app.register('symfony--ux-chartjs--chart', ChartjsController);

    // Safety net for "Canvas is already in use", a canvas reconnected by Turbo still meeting a live Chart. "chartjs:pre-connect" is ux-chartjs's own public event, fired on the canvas before its Chart()
    document.addEventListener('chartjs:pre-connect', (event) => {
        Chart.getChart(event.target)?.destroy();
    });
}
