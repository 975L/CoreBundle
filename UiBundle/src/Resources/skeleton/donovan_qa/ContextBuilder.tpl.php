<?= "<?php\n" ?>
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace <?= $namespace ?>;

use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\UiBundle\Registry\BlockRegistry;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Translation\TranslatorInterface;

// One prompt section per registered block kind and per guided project, so the model cites real kinds and real parcours instead of hallucinating one
class <?= $class_name ?>
{
    // The context is built once for every asking site, none of them being the locale of any of their users
    private const string LOCALE = 'en';

    // Marks a guided project apart from a block kind in what the model cites back - a bare identifier stays a block, which is what the answers cached before parcours existed carry
    private const string TOUR_PREFIX = 'tour:';

    // Built once per request: every provider generates its own urls, and both the sections and the sources ask for the same list
    private ?array $projects = null;

    public function __construct(
        private readonly BlockRegistry $blockRegistry,
        // The providers rather than ConfigBundle's GuidedProjectBuilder: that one drops every project carrying a role the current user lacks, and this runs on a token-authenticated request with no user at all
        #[AutowireIterator('c975l.guided_project_provider')]
        private readonly iterable $guidedProjectProviders,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function context(): string
    {
        return implode("\n\n", [...$this->blockSections(), ...$this->tourSections()]);
    }

    // @param string[] $kinds
    // @return array{label: string, url: string, project?: string}[]
    public function resolveSources(array $kinds): array
    {
        $sources = [];
        foreach ($kinds as $kind) {
            if (str_starts_with($kind, self::TOUR_PREFIX)) {
                $source = $this->resolveTour(substr($kind, \strlen(self::TOUR_PREFIX)));
            } else {
                $source = $this->resolveBlock($kind);
            }

            if (null !== $source) {
                $sources[] = $source;
            }
        }

        return $sources;
    }

    private function blockSections(): array
    {
        $sections = [];
        foreach (array_keys($this->blockRegistry->all()) as $kind) {
            $label = $this->blockRegistry->getLabel($kind);
            $description = $this->blockRegistry->getDescription($kind);
            $sections[] = "### {$kind}\nLabel: {$label}\nDescription: {$description}";
        }

        return $sections;
    }

    // Labels and descriptions are translation keys on a provider, resolved here or the model would read "label.project_media"
    private function tourSections(): array
    {
        $sections = [];
        foreach ($this->guidedProjects() as $project) {
            $domain = $project['translation_domain'];
            $steps = array_map(fn (array $step) => '- ' . $this->translator->trans($step['label'], [], $domain, self::LOCALE), $project['steps']);

            $sections[] = '### ' . self::TOUR_PREFIX . $project['slug'] . "\n"
                . 'Label: ' . $this->translator->trans($project['label'], [], $domain, self::LOCALE) . "\n"
                . 'Description: ' . $this->translated($project['description'] ?? null, $domain) . "\n"
                . "Steps:\n" . implode("\n", $steps);
        }

        return $sections;
    }

    private function guidedProjects(): array
    {
        if (null !== $this->projects) {
            return $this->projects;
        }

        $projects = [];
        foreach ($this->guidedProjectProviders as $provider) {
            if ($provider instanceof GuidedProjectProviderInterface) {
                $projects = [...$projects, ...$provider->getGuidedProjects()];
            }
        }

        return $this->projects = $projects;
    }

    // No url: the asking site resolves the slug against its own parcours, the answer being cached for every site at once
    private function resolveTour(string $slug): ?array
    {
        foreach ($this->guidedProjects() as $project) {
            if ($slug === $project['slug']) {
                return [
                    'label' => $this->translator->trans($project['label'], [], $project['translation_domain'], self::LOCALE),
                    'url' => '',
                    'project' => $slug,
                ];
            }
        }

        return null;
    }

    private function resolveBlock(string $kind): ?array
    {
        if (!$this->blockRegistry->has($kind)) {
            return null;
        }

        return [
            'label' => $this->blockRegistry->getLabel($kind),
            // TODO: point this at your own block gallery, if you have one
            'url' => '',
        ];
    }

    private function translated(?string $key, string $domain): string
    {
        return empty($key) ? '' : $this->translator->trans($key, [], $domain, self::LOCALE);
    }
}
