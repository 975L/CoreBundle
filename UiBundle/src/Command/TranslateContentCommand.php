<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Command;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\UiBundle\Contract\TranslatableTextProviderInterface;
use c975L\UiBundle\Service\AiRephraseClient;
use c975L\UiBundle\Service\ContentTranslator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

// Writes what the site says - its pages, menus, forms, whatever each bundle hands over (see TranslatableTextProviderInterface) - in another language one field at a time, through the same services as the back office "Translate" screens and its Donovan button, a field already written being left alone unless --force is passed
#[AsCommand(
    name: 'c975l:translate:content',
    description: 'Translates what the site says - pages, blocks, menus, forms - into another declared language',
)]
class TranslateContentCommand extends Command
{
    // Waited between two calls: a provider selling its api by the minute answers 429 to a loop that asks as fast as PHP can, and half the run comes back empty (Infomaniak's Euria does)
    private const int PAUSE = 1500;

    // Waited before asking again for a text the provider refused, the rate limit being what a first failure most often means
    private const int RETRY_PAUSE = 15000;

    // What this run has already had translated, by source text: a sentence repeated on page after page gets one call and one wording, where asking again gave eight wordings of the same call-to-action
    /** @var array<string, string> */
    private array $said = [];

    /** @param iterable<TranslatableTextProviderInterface> $providers */
    public function __construct(
        #[AutowireIterator('c975l.translatable_text_provider')]
        private readonly iterable $providers,
        private readonly ContentTranslator $contentTranslator,
        private readonly AiRephraseClient $aiRephraseClient,
        private readonly SiteLocales $siteLocales,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Language to write, one the site declares')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED, 'Only the texts stored under that owner, "site_page" or "ui_form_field" for instance')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Lists what would be translated, calls nothing')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Translates again what is already written in that language')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stops after that many fields')
            ->addOption('pause', null, InputOption::VALUE_REQUIRED, 'Milliseconds waited between two calls', (string) self::PAUSE)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $translatable = $this->siteLocales->translatable();
        if ([] === $translatable) {
            $io->error('The site declares a single language: there is nothing to translate (see framework.enabled_locales).');

            return Command::FAILURE;
        }

        $locale = (string) ($input->getOption('locale') ?? $translatable[0]);
        if (!\in_array($locale, $translatable, true)) {
            $io->error(sprintf('"%s" is not a language this site declares: %s.', $locale, implode(', ', $translatable)));

            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $force = (bool) $input->getOption('force');
        $owner = $input->getOption('owner');
        $limit = null !== $input->getOption('limit') ? max(1, (int) $input->getOption('limit')) : null;

        $rows = $this->rows(\is_string($owner) ? $owner : null);
        $rows = $force ? $rows : array_values(array_filter($rows, fn (array $row): bool => !$this->isWritten($row, $locale)));

        if (null !== $limit) {
            $rows = \array_slice($rows, 0, $limit);
        }

        if ([] === $rows) {
            $io->success(sprintf('Nothing left to write in %s.', $locale));

            return Command::SUCCESS;
        }

        $io->title(sprintf('%d text%s to write in %s', \count($rows), \count($rows) > 1 ? 's' : '', $locale));

        if ($dryRun) {
            $io->table(['Owner', 'Field', 'Text'], array_map(
                fn (array $row): array => [$row['label'], $row['field'], $this->preview($row['source'])],
                $rows,
            ));

            return Command::SUCCESS;
        }

        // Checked here rather than above: a dry run says what is left to do on a site whose key is not filled yet
        if (!$this->aiRephraseClient->isEnabled()) {
            $io->error('The translation key is not configured: fill the "ui-ai-assistant-writer-*" entries in the back office.');

            return Command::FAILURE;
        }

        return $this->write($io, $rows, $locale, max(0, (int) $input->getOption('pause')));
    }

    // Translates each text and stores it as it goes, one field per call, so an interrupted run keeps what it paid for and the next one resumes there
    /** @param list<array{owner: string, ownerId: int, field: string, source: string, label: string}> $rows */
    private function write(SymfonyStyle $io, array $rows, string $locale, int $pause): int
    {
        $failed = [];
        $suspect = [];
        $written = 0;

        $io->progressStart(\count($rows));

        foreach ($rows as $row) {
            $translated = $this->translate($row['source'], $locale, $pause);

            // Null is the client's own way of saying the call failed: nothing is stored, and the field stays to be done on the next run
            if (null === $translated || '' === trim($translated)) {
                $failed[] = $row['label'] . ' — ' . $row['field'];
                $io->progressAdvance();

                continue;
            }

            // A rich text that came back without its markup is stored all the same, then named below: it is a translation, only one whose formatting has to be looked at
            if (str_contains($row['source'], '<') && !str_contains($translated, '<')) {
                $suspect[] = $row['label'] . ' — ' . $row['field'];
            }

            $this->contentTranslator->store($row['owner'], $row['ownerId'], $locale, [$row['field'] => $translated]);
            ++$written;
            $io->progressAdvance();
        }

        $io->progressFinish();

        if ([] !== $suspect) {
            $io->warning(sprintf("These texts lost their formatting on the way, check them in the back office:\n- %s", implode("\n- ", $suspect)));
        }

        if ([] !== $failed) {
            $io->error(sprintf("These texts were not translated, run the command again to retry them:\n- %s", implode("\n- ", $failed)));
        }

        $io->success(sprintf('%d text%s written in %s.', $written, $written > 1 ? 's' : '', $locale));

        return [] === $failed ? Command::SUCCESS : Command::FAILURE;
    }

    // One text translated a cell at a time, a comparison table's "✓ | rare" coming back as "rare" once asked whole: the neutral cells are kept as they are, the others asked for, and the text fails as soon as one of them does
    private function translate(string $source, string $locale, int $pause): ?string
    {
        $parts = [];

        foreach (explode(' | ', $source) as $part) {
            $parts[] = self::isNeutral($part) ? $part : $this->translatePart($part, $locale, $pause);

            if (null === end($parts)) {
                return null;
            }
        }

        return implode(' | ', $parts);
    }

    // What reads the same in every language and is never sent: no letter at all ("✓", "✗", "12 €"), or a domain name ("run.as" came back as "run.es", its title as "correr.com")
    public static function isNeutral(string $text): bool
    {
        $text = trim($text);

        return 1 !== preg_match('/\p{L}/u', $text) || 1 === preg_match('/^[\w-]+(\.[\w-]+)+$/u', $text);
    }

    // One text asked for, asked a second time after the long wait when the first call comes back empty, since a null cannot tell a refusal from a rate limit ("--pause=0" waits nowhere, second try included)
    private function translatePart(string $source, string $locale, int $pause): ?string
    {
        $key = $locale . "\0" . $source;

        if (isset($this->said[$key])) {
            return $this->said[$key];
        }

        $translated = $this->refused($this->aiRephraseClient->translate($source, $locale), $locale);

        if (null === $translated) {
            if ($pause > 0) {
                usleep(self::RETRY_PAUSE * 1000);
            }

            $translated = $this->refused($this->aiRephraseClient->translate($source, $locale), $locale);
        }

        usleep($pause * 1000);

        // Only what came back: a failed call is left to the next run rather than remembered as this one's answer
        if (null !== $translated) {
            $this->said[$key] = $translated;
        }

        return $translated;
    }

    // What the provider said, or null for an empty answer or the target language's own code ("Nom" once came back as "EN" and became a form label)
    private function refused(?string $translated, string $locale): ?string
    {
        $answer = trim((string) $translated);

        return '' === $answer || 0 === strcasecmp($answer, $locale) ? null : $answer;
    }

    // Every text the site has to say again in another language, as each bundle hands it over, narrowed to one owner when asked
    /** @return list<array{owner: string, ownerId: int, field: string, source: string, label: string}> */
    private function rows(?string $owner): array
    {
        $rows = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->getTranslatableTexts() as $row) {
                if (null === $owner || $owner === $row['owner']) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    // Whether that field already says something in that language, an entry opened then left blank counting for nothing
    /** @param array{owner: string, ownerId: int, field: string, source: string, label: string} $row */
    private function isWritten(array $row, string $locale): bool
    {
        $value = $this->contentTranslator->values($row['owner'], $row['ownerId'], $locale)[$row['field']] ?? null;

        return \is_string($value) && '' !== trim($value);
    }

    // The first words of a text, tags out, for the dry run's table
    private function preview(string $source): string
    {
        $plain = trim((string) preg_replace('/\s+/', ' ', strip_tags($source)));

        return mb_strlen($plain) > 60 ? mb_substr($plain, 0, 60) . '…' : $plain;
    }
}
