<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Command;

use c975L\UiBundle\Service\LayoutAuditor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reads a rendered page's geometry and reports what no sass test can see - a component pushed off-centre,
 * a block breaking out of the frame, an image blown up past the window.
 *
 * Reports by default: a headless browser is never deterministic enough to gate a push on, and a check that
 * fails at random is a check that ends up disabled. Pass --strict to make it exit non-zero.
 *
 * Needs chrome-php/chrome (a require-dev of this bundle) and a local Chrome - it exits cleanly saying so
 * when either is missing, so a site that never installed them is never broken by this command existing.
 *
 * Usage:
 *   php bin/console c975l:ui:layout-audit https://bundles.975l.com/galerie
 *   php bin/console c975l:ui:layout-audit http://127.0.0.1:8000/ --width=390 --width=1280 --strict
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:ui:layout-audit',
    description: 'Measures a rendered page against the layout invariants the sass tests cannot check'
)]
class LayoutAuditCommand extends Command
{
    public function __construct(
        private readonly LayoutAuditor $layoutAuditor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('urls', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The pages to measure');
        $this->addOption('width', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Viewport widths, repeatable', LayoutAuditor::DEFAULT_WIDTHS);
        $this->addOption('strict', null, InputOption::VALUE_NONE, 'Exits non-zero when anything is found, instead of only reporting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Missing on a site that never installed the optional dependency, which is every deployed site
        if (!LayoutAuditor::isAvailable()) {
            $io->warning('chrome-php/chrome n\'est pas installé, audit ignoré (composer require --dev chrome-php/chrome).');

            return Command::SUCCESS;
        }

        $widths = array_map('intval', (array) $input->getOption('width'));
        $findings = [];
        $unmeasured = 0;

        foreach ((array) $input->getArgument('urls') as $url) {
            $io->section((string) $url);

            try {
                $found = $this->layoutAuditor->audit((string) $url, $widths);
            } catch (\Throwable $e) {
                // A browser that would not start is a broken tool, not a broken page - it is not a finding, but it is not a clean page either
                $io->warning(sprintf('Page non mesurée (%s) : %s', $url, $e->getMessage()));
                ++$unmeasured;

                continue;
            }

            $this->report($io, $found);
            $findings = array_merge($findings, $found);
        }

        // Announcing a clean page for one that was never looked at is the one outcome worse than a false positive
        if ([] === $findings) {
            $unmeasured > 0
                ? $io->warning(sprintf('%d page(s) non mesurée(s), rien ne peut être conclu.', $unmeasured))
                : $io->success('Aucun écart de mise en page détecté.');

            return Command::SUCCESS;
        }

        $io->warning(sprintf('%d écart(s) de mise en page détecté(s).', count($findings)));

        return $input->getOption('strict') ? Command::FAILURE : Command::SUCCESS;
    }

    private function report(SymfonyStyle $io, array $findings): void
    {
        if ([] === $findings) {
            $io->text('<info>Rien à signaler.</info>');

            return;
        }

        $io->table(
            ['Largeur', 'Contrôle', 'Élément', 'Constat'],
            array_map(static fn (array $finding): array => [
                $finding['width'] . 'px',
                $finding['check'],
                // Long enough to identify the element, short enough to keep the table readable
                mb_strimwidth($finding['selector'], 0, 60, '…'),
                $finding['summary'],
            ], $findings)
        );
    }
}
