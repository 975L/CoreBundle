<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Command;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Repository\BlockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Moves the two "other ..." legal texts out of the configuration and into the model they were appended to.
 *
 * They used to be config entries the cookies and copyright models read through config() and rendered as a section
 * of their own. An added section does exactly that, better: it belongs to the model rather than to the site, it is
 * written on the screen where the rest of that document is written, and it lives in the block's own data - which is
 * translated, where a config entry is not. Two mechanisms for one need, and this takes the older one away.
 *
 * A one-off, unlike c975l:ui:email-templates:ensure beside it in the deployment: once a site has been through it
 * there is nothing left to move, and it says so. The line calling it, and this command, are meant to be taken out
 * once the whole fleet has run it.
 */
#[AsCommand(
    name: 'c975l:ui:legal-models:adopt-config-sections',
    description: 'Moves the "site-other-cookies"/"site-other-copyright" config entries into the legal models as added sections'
)]
class LegalModelAdoptConfigSectionsCommand extends Command
{
    /**
     * Config slug => the model it was appended to, the id the section takes, and its heading per language.
     *
     * The wording is the very one the models printed above it (see templates/models/france/cookies.*.html.twig), so
     * a reader sees the same title before and after - and the identifier is that section's own, so a second run
     * recognises what the first one wrote instead of adding it again.
     */
    private const array MOVES = [
        'site-other-cookies' => [
            'model' => 'france/cookies',
            'id' => 'other-cookies',
            'titles' => ['fr' => 'Autres cookies', 'en' => 'Other cookies', 'es' => 'Otras cookies'],
        ],
        'site-other-copyright' => [
            'model' => 'france/copyright',
            'id' => 'other-copyrights',
            'titles' => ['fr' => "Autres droits d'auteur", 'en' => 'Other Copyrights', 'es' => 'Otros derechos de autor'],
        ],
    ];

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly BlockRepository $blockRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $moved = [];
        $stranded = [];

        foreach (self::MOVES as $slug => $move) {
            // Read from the repository and not through ConfigService: no configs.json declares the entry any more, so the service does not know it - the row itself is still there, nothing in a deployment taking it away
            $config = $this->configRepository->findOneBySlug($slug);
            $content = trim((string) $config?->getValue());

            // An entry nobody ever filled has no text to save, so it simply goes
            if (null === $config || '' === $content) {
                if ($config instanceof Config) {
                    $this->entityManager->remove($config);
                }

                continue;
            }

            $blocks = $this->blocksOf($move['model']);

            // Nothing to append it to: a site that filled the entry without ever creating the page that shows it.
            // The row is left where it is rather than deleted, this being the only copy of a text somebody wrote
            if ([] === $blocks) {
                $stranded[] = sprintf('%s (no "%s" block on this site)', $slug, $move['model']);

                continue;
            }

            foreach ($blocks as $block) {
                if ($this->append($block, $move, $content)) {
                    $moved[] = sprintf('%s -> block #%d', $slug, (int) $block->getId());
                }
            }

            $this->entityManager->remove($config);
        }

        $this->entityManager->flush();

        if ([] !== $stranded) {
            $io->warning('Left in place, having nowhere to go - move the text by hand, then delete the entry:');
            $io->listing($stranded);
        }

        if ([] === $moved) {
            $io->success('Nothing to move.');

            return Command::SUCCESS;
        }

        $io->listing($moved);
        $io->success(sprintf('%d section(s) moved out of the configuration.', \count($moved)));

        return Command::SUCCESS;
    }

    /**
     * The legal_model blocks rendering one model.
     *
     * @return list<Block>
     */
    private function blocksOf(string $model): array
    {
        return array_values(array_filter(
            $this->blockRepository->findByKind('legal_model'),
            static fn (Block $block): bool => $model === ($block->getData()['model'] ?? null),
        ));
    }

    // Appends the section to a block's customization, and says whether it had to be written - a block already carrying it is left exactly as it is, so a second run adds nothing
    private function append(Block $block, array $move, string $content): bool
    {
        // The delta the screen writes sits under "customization" and not at the root of the block's data (see LegalDocument::render, which reads that very key)
        $data = $block->getData();
        $customization = (array) ($data['customization'] ?? []);
        $extra = (array) ($customization['extra'] ?? []);

        foreach ($extra as $section) {
            if (\is_array($section) && $move['id'] === ($section['id'] ?? null)) {
                return false;
            }
        }

        $extra[] = [
            'id' => $move['id'],
            // Top level, where the models printed it: after the last section of the document
            'parent' => '',
            'title' => $move['titles'][$this->defaultLocale] ?? $move['titles']['en'],
            // The models printed it through "|raw|nl2br": an added section is rendered as the html it holds, and would otherwise run every typed line into the next
            'content' => nl2br($content),
        ];

        $customization['extra'] = array_values($extra);
        $data['customization'] = $customization;
        $block->setData($data);
        $this->entityManager->persist($block);

        return true;
    }
}
