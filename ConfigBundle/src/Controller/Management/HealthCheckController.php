<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller\Management;

use c975L\ConfigBundle\Command\HealthCheckRunCommand;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\AiCrawlersHealthCheckProvider;
use c975L\ConfigBundle\Management\AlertBuilder;
use c975L\ConfigBundle\Management\BackupResultRecorder;
use c975L\ConfigBundle\Management\DatabaseLoadHealthCheckProvider;
use c975L\ConfigBundle\Management\HealthCheckAdviceBuilder;
use c975L\ConfigBundle\Management\HealthCheckReportBuilder;
use c975L\ConfigBundle\Management\HealthCheckRunner;
use c975L\ConfigBundle\Management\HealthCheckRunProgress;
use c975L\ConfigBundle\Management\HealthCheckTrendChartBuilder;
use c975L\ConfigBundle\Management\SitemapRobotsHealthCheckProvider;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class HealthCheckController extends AbstractController
{
    // EasyAdmin prefixes this with the Dashboard's own route name, giving management_health_check_run
    public const RUN_ROUTE = 'management_health_check_run';

    // Same prefixing, giving management_health_check_acknowledge - the token the table's acknowledge button sends is minted under this very name (see _table.html.twig)
    public const ACKNOWLEDGE_ROUTE = 'management_health_check_acknowledge';

    // Kinds checked once for the whole site (infrastructure-level: TLS cert, security headers, security misconfiguration, robots.txt/sitemap, sitemaps cross-checked against robots.txt, redirect chains, http/https + 404 deployment checks, database load, last backup, uploaded svg files, declared files, ai crawlers list) rather than once per page - shown in their own "Site" section instead of the per-page table, see index(). Not the whole answer: a bundle installed beside this one cannot add to a list written here, and says so on its provider instead (see HealthCheckSiteWideInterface), the two being merged by siteWideKinds()
    private const array SITE_WIDE_KINDS = ['security-headers', 'security-misconfig', 'ssl-certificate', 'seo-files', 'redirect-chains', 'deployment', 'svg-fonts', 'files-ui', AiCrawlersHealthCheckProvider::KIND, DatabaseLoadHealthCheckProvider::KIND, BackupResultRecorder::KIND, SitemapRobotsHealthCheckProvider::KIND];

    public function __construct(
        private readonly HealthCheckResultRepository $healthCheckResultRepository,
        private readonly HealthCheckRunner $healthCheckRunner,
        private readonly AlertBuilder $alertBuilder,
        private readonly HealthCheckAdviceBuilder $healthCheckAdviceBuilder,
        private readonly HealthCheckReportBuilder $healthCheckReportBuilder,
        private readonly TableExporter $tableExporter,
        private readonly HealthCheckTrendChartBuilder $healthCheckTrendChartBuilder,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly MessageBusInterface $messageBus,
        private readonly HealthCheckRunProgress $healthCheckRunProgress,
        private readonly EntityManagerInterface $manager,
    ) {
    }

    // Custom admin page (not tied to any entity), registered under the Dashboard's own route path/name, giving /management/health-check and management_health_check_index. Reads the latest persisted results only - a GET here never triggers a live check (see run() below and HealthCheckRunner, also run periodically from c975l:health-check:run)
    #[AdminRoute(path: '/health-check', name: 'health_check_index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        // The run this admin queued, while it still has jobs to land - handed to the template so the progress banner survives the reloads it triggers itself (see progress() and the health-check-progress controller), a flash message being shown once and gone. Polled before the results are read, and not after: a row landing between the two would otherwise be missing from the tables while the run had just been seen finishing, leaving a stale page with no banner left to reload it
        $runProgress = $this->healthCheckRunProgress->poll();
        $runProgress = $runProgress && !$runProgress['finished'] ? $runProgress : null;

        $results = $this->healthCheckResultRepository->findLatestPerUrlAndKind();

        // Site-wide kinds (see SITE_WIDE_KINDS) are checked once for the whole site, never per-page like the rest - mixed into the same table they'd read as one page among many instead of the site-wide result they actually are, so they're pulled out into their own "Site" section instead
        $siteResults = [];
        $pageResults = [];
        $siteWideKinds = $this->siteWideKinds();
        foreach ($results as $result) {
            if (\in_array($result->getKind(), $siteWideKinds, true)) {
                $siteResults[] = $result;
                continue;
            }
            $pageResults[] = $result;
        }

        return $this->render(
            '@c975LConfig/management/health_check/index.html.twig',
            [
                'results' => $pageResults,
                // Distinct kinds across the current page results, for the table's "kind" filter dropdown - computed here rather than in Twig, which has no built-in "unique" filter
                'kinds' => array_values(array_unique(array_map(static fn (HealthCheckResult $result) => $result->getKind(), $pageResults))),
                'siteResults' => $siteResults,
                'siteKinds' => array_values(array_unique(array_map(static fn (HealthCheckResult $result) => $result->getKind(), $siteResults))),
                // Same dashboard-wide list as management/index.html.twig (not filtered to health-check-specific alerts, there's no such category today) - so a config a HealthCheckProvider depends on (e.g. healthcheck-pagespeed-api-key) is visible here too, not just on the dashboard
                'alerts' => $this->alertBuilder->getAlerts(),
                'trendChart' => $this->healthCheckTrendChartBuilder->build(),
                // Every page is checked in the same run (see HealthCheckRunner::run()), so a per-row date is redundant - shown once above the table instead, taking the most recent in case a kind was re-run on its own. The backup rows are left out: they land here every few hours on a scheduler of their own, and would keep this date fresh long after the health-check one had stopped running - the very failure it's here to make visible
                'lastCheckedAt' => $this->latestCheckedAt(array_filter(
                    $results,
                    static fn (HealthCheckResult $result) => BackupResultRecorder::KIND !== $result->getKind()
                )),
                // Built once across every result (site + page) and handed to both table includes below - the same shared table (health_check/_table.html.twig) any CRUD's own "Health check" tab uses, so advice reads identically everywhere
                'advice' => $this->healthCheckAdviceBuilder->build($results),
                'runProgress' => $runProgress,
            ]
        );
    }

    // Queues the run instead of executing it, one job per provider kind: a single provider can hold thousands of urls (a gallery declares one per photo), which no admin request can wait for without timing out - and a run that times out persists nothing at all. The jobs are the very console command the scheduler already runs, so nothing new has to be wired beyond routing RunCommandMessage to an async transport (see the readme). Results land on this page as each job completes, and HealthCheckAlertProvider raises what needs attention on the dashboard
    #[AdminRoute(
        path: '/health-check/run',
        name: 'health_check_run',
        options: ['methods' => ['POST']]
    )]
    public function run(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if ($this->isCsrfTokenValid(self::RUN_ROUTE, $request->request->get('_token'))) {
            $kinds = $this->healthCheckRunner->getKinds();

            // Before the first dispatch, never after: a worker already listening records its first kind while this loop is still running, and a sync transport runs every job inside dispatch() itself - started afterwards, the run would be following a moment its own results already predate, and could never be seen finishing
            $this->healthCheckRunProgress->start($kinds);

            foreach ($kinds as $kind) {
                $this->messageBus->dispatch(new RunCommandMessage(HealthCheckRunCommand::NAME . ' --kind=' . $kind));
            }

            $this->addFlash('success', $this->translator->trans(
                'flash.health_check_queued',
                ['%count%' => \count($kinds)],
                'config',
            ));
        } else {
            $this->addFlash('danger', $this->translator->trans('flash.health_check_run_invalid_token', [], 'config'));
        }

        return $this->redirectToRoute('management_health_check_index');
    }

    // Declares one row dealt with, or takes that back - what an admin who just fixed something has instead of re-running the whole check, a run costing minutes and hitting every external platform again. Toggles rather than sets, an acknowledgement clicked by mistake being otherwise unrecoverable from the screen it was made on. Answers JSON and redirects nothing: the table updates the row in place (see the health-check-table Stimulus controller), the point being not to reload a page the admin is working through
    #[AdminRoute(
        path: '/health-check/{id}/acknowledge',
        name: 'health_check_acknowledge',
        options: ['methods' => ['POST'], 'requirements' => ['id' => '\\d+']]
    )]
    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (!$this->isCsrfTokenValid(self::ACKNOWLEDGE_ROUTE, $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => $this->translator->trans('flash.health_check_run_invalid_token', [], 'config')], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->healthCheckResultRepository->find($id);
        if (null === $result) {
            return $this->json(['error' => $this->translator->trans('label.no_health_check', [], 'config')], Response::HTTP_NOT_FOUND);
        }

        $result->setAcknowledgedAt($result->isAcknowledged() ? null : new \DateTime());
        $this->manager->flush();

        return $this->json(['acknowledged' => $result->isAcknowledged()]);
    }

    // How far along the run this admin queued is, polled by the progress banner (see the health-check-progress Stimulus controller) - the jobs run in a Messenger worker, so the page has no other way of telling a run still going from one whose worker was never started. Returns a finished run when there's nothing being followed, the banner then having nothing left to wait for
    #[AdminRoute(path: '/health-check/progress', name: 'health_check_progress')]
    public function progress(): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->json(
            $this->healthCheckRunProgress->poll() ?? ['done' => 0, 'total' => 0, 'finished' => true, 'timedOut' => false]
        );
    }

    // The list above plus whatever the installed providers declare for themselves - one answer, so index() reads it once for the whole page
    private function siteWideKinds(): array
    {
        return array_values(array_unique([...self::SITE_WIDE_KINDS, ...$this->healthCheckRunner->getSiteWideKinds()]));
    }

    // Dated CSV snapshot of the current results (one row per url/kind, see HealthCheckResultRepository::findLatestPerUrlAndKind()) - the audit-trail artefact for accessibility declarations (RGAA/EAA): each row already carries its own checkedAt, and TableExporter dates the filename itself, so re-exporting weekly/monthly builds a paper trail without any extra bookkeeping here. Unlike index(), site-wide kinds (see SITE_WIDE_KINDS) are deliberately kept in the export rather than split out - completeness matters more than the dashboard's readability concern here, and the 'kind' column already discloses which rows are site-wide
    #[AdminRoute(path: '/health-check/export', name: 'health_check_export')]
    public function exportCsv(): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        $rows = array_map(
            static fn (HealthCheckResult $result) => [
                'kind' => $result->getKind(),
                'url' => $result->getUrl(),
                'label' => $result->getLabel(),
                'status' => $result->getStatus(),
                'summary' => $result->getSummary(),
                'checkedAt' => $result->getCheckedAt()->format('Y-m-d H:i:s'),
                // Empty on all but the rows an admin declared dealt with - the export being the audit artefact, a row leaving the dashboard's default view must still say so here rather than simply disappear
                'acknowledgedAt' => $result->getAcknowledgedAt()?->format('Y-m-d H:i:s') ?? '',
            ],
            $this->healthCheckResultRepository->findLatestPerUrlAndKind(),
        );

        return $this->tableExporter->export(ExportFormat::Csv, 'health_check', $rows);
    }

    // The same run as the CSV above, as the report whoever fixes the site reads: every row needing action with the checkers' own details, under the versions this site runs (see HealthCheckReportBuilder). The CSV is the audit artefact - one line per url/kind, opened in a spreadsheet, kept as a dated trace; this one is the diagnosis, and a "details" column is what no spreadsheet ever reads.
    //
    // Downloaded rather than served inline: it is meant to be handed over - attached to a ticket, dropped into an assistant - and a browser rendering json in a tab is a page to copy out of rather than a file to pass on
    #[AdminRoute(path: '/health-check/report', name: 'health_check_report')]
    public function report(): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        $response = new JsonResponse($this->healthCheckReportBuilder->build());
        $response->setEncodingOptions(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        // Dated the way TableExporter names its own files, so a report and the CSV taken beside it sort together. It doesn't go through the exporter itself: that one encodes a table of rows, and this is a document
        $response->headers->set('Content-Disposition', 'attachment; filename="health_check_report_' . date('Ymd_His') . '.json"');

        // The site's own state, read by whoever is fixing it: never a shared cache's to hold
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    // @param HealthCheckResult[] $results
    private function latestCheckedAt(array $results): ?\DateTimeInterface
    {
        if ([] === $results) {
            return null;
        }

        return max(array_map(static fn (HealthCheckResult $result) => $result->getCheckedAt(), $results));
    }
}
