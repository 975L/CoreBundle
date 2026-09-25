<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\UiBundle\Controller\Management\AiAssistantController;
use c975L\UiBundle\Controller\Management\LegalModelController;
use c975L\UiBundle\Management\UiGuidedProjectProvider;
use c975L\UiBundle\Service\AiRephraseClient;
use c975L\UiBundle\Service\AiSiteSearchClient;
use c975L\UiBundle\Service\ReviewService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UiGuidedProjectProviderTest extends TestCase
{
    private function createReviewService(bool $enabled = true): ReviewService
    {
        $reviewService = $this->createStub(ReviewService::class);
        $reviewService->method('isEnabled')->willReturn($enabled);

        return $reviewService;
    }

    private function createAdminUrlGenerator(array &$controllers = []): AdminUrlGeneratorInterface
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnCallback(function (string $controller) use ($generator, &$controllers) {
            $controllers[] = $controller;

            return $generator;
        });
        $generator->method('setAction')->willReturnSelf();
        $generator->method('set')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/management/x');

        return $generator;
    }

    // One value per key rather than a single one for all of them: the projects do not share a role, and a stub answering the same thing everywhere would let a project ask for the wrong one unnoticed
    private function createConfigService(): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): string => match ($key) {
            'site-role-editor' => 'ROLE_EDITOR',
            'site-role-admin' => 'ROLE_ADMIN',
            default => throw new \InvalidArgumentException(sprintf('Unexpected config key "%s"', $key)),
        });

        return $configService;
    }

    // The legal documents screen is reached by route name, not through EasyAdmin's generator - recorded the same way, so a renamed route shows up here too
    private function createUrlGenerator(array &$routes = []): UrlGeneratorInterface
    {
        $generator = $this->createStub(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(function (string $route) use (&$routes): string {
            $routes[] = $route;

            return '/management/' . $route;
        });

        return $generator;
    }

    private function createSiteSearchClient(bool $enabled): AiSiteSearchClient
    {
        $client = $this->createStub(AiSiteSearchClient::class);
        $client->method('isEnabled')->willReturn($enabled);

        return $client;
    }

    private function createRephraseClient(bool $enabled): AiRephraseClient
    {
        $client = $this->createStub(AiRephraseClient::class);
        $client->method('isEnabled')->willReturn($enabled);

        return $client;
    }

    // Multilingual and with the site search and the rephrasing configured unless told otherwise, so every step the provider can walk is there for the assertions below to read
    private function createProvider(array &$controllers = [], array &$routes = [], bool $multilingual = true, bool $siteSearch = true, bool $rephrase = true): UiGuidedProjectProvider
    {
        return new UiGuidedProjectProvider($this->createAdminUrlGenerator($controllers), $this->createConfigService(), $this->createUrlGenerator($routes), $this->createReviewService(), new SiteLocales($multilingual ? ['fr', 'en'] : [], 'fr'), $this->createSiteSearchClient($siteSearch), $this->createRephraseClient($rephrase));
    }

    // The screen draws either the list of what is missing or the textarea, so the parcours walks the one it will find and never both
    public function testTheAiAssistantProjectWalksTheSetupListOrTheTextareaNeverBoth(): void
    {
        $unconfigured = array_column($this->aiAssistantSteps(false), 'highlight');
        $configured = array_column($this->aiAssistantSteps(true), 'highlight');

        $this->assertSame(['[data-ai-rephrase-setup]'], $unconfigured);
        $this->assertSame(['#ai-rephrase-freeform', '.ai-rephrase__style', '.ai-rephrase__length', '.ai-rephrase__button'], $configured);
    }

    // The closing step speaks of the button under every field only once the button was shown, a site yet to be set up is asked to come back instead
    public function testTheAiAssistantProjectClosesOnTheStepItsBranchEarned(): void
    {
        $unconfigured = array_column($this->aiAssistantSteps(false), 'label');
        $configured = array_column($this->aiAssistantSteps(true), 'label');

        $this->assertSame('label.guided_step_ui_ai_assistant_setup_done', end($unconfigured));
        $this->assertNotContains('label.guided_step_ui_ai_assistant_done', $unconfigured);
        $this->assertSame('label.guided_step_ui_ai_assistant_done', end($configured));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function aiAssistantSteps(bool $rephrase): array
    {
        $controllers = [];
        $routes = [];
        foreach ($this->createProvider($controllers, $routes, rephrase: $rephrase)->getGuidedProjects() as $project) {
            if ('ui-ai-assistant' === $project['slug']) {
                return $project['steps'];
            }
        }

        self::fail('The "ui-ai-assistant" guided project was not found.');
    }

    // No question is recorded before the search is configured, so the parcours reading them is not offered - the one setting it up is, whatever the state
    public function testTheSearchAnswersProjectWaitsForTheSearchToBeConfigured(): void
    {
        $controllers = [];
        $routes = [];
        $slugs = array_column($this->createProvider($controllers, $routes, siteSearch: false)->getGuidedProjects(), 'slug');

        $this->assertContains('ui-ai-search-setup', $slugs);
        $this->assertNotContains('ui-ai-search-answers', $slugs);
    }

    // The tabs are on the edit screen and saving a new form returned to the index, so the parcours reopens the form before pointing at them - as the calculator's reopens it for its formulas
    public function testTheFormProjectReopensTheFormBeforePointingAtTheLanguageTabs(): void
    {
        $steps = array_column($this->formSteps(true), null, 'label');

        $this->assertSame('.action-edit', $steps['label.guided_step_ui_form_reopen']['highlight']);
        $this->assertSame('[data-content-locales]', $steps['label.guided_step_ui_form_translate']['highlight']);
        $this->assertSame(
            ['label.guided_step_ui_form_save', 'label.guided_step_ui_form_reopen', 'label.guided_step_ui_form_translate'],
            \array_slice(array_keys($steps), 7, 3),
        );
    }

    // On a site declaring a single language no tab is ever drawn, and neither step walking to them is offered
    public function testASingleLanguageSiteWalksNoLanguageStepOfTheFormProject(): void
    {
        $labels = array_column($this->formSteps(false), 'label');

        $this->assertNotContains('label.guided_step_ui_form_reopen', $labels);
        $this->assertNotContains('label.guided_step_ui_form_translate', $labels);
        $this->assertContains('label.guided_step_ui_form_place', $labels);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formSteps(bool $multilingual): array
    {
        $controllers = [];
        $routes = [];
        foreach ($this->createProvider($controllers, $routes, $multilingual)->getGuidedProjects() as $project) {
            if ('ui-form' === $project['slug']) {
                return $project['steps'];
            }
        }

        self::fail('The "ui-form" guided project was not found.');
    }

    // The 3000 block GuidedProjectProviderInterface reserves this bundle, at the step of 10 it states, the site search slipped in beside the rephrasing's key it is set with
    public function testGetGuidedProjectsContinuesTheOrderSequence(): void
    {
        $projects = $this->createProvider()->getGuidedProjects();

        $this->assertSame(['ui-media', 'ui-site-graphic', 'ui-legal-model', 'ui-ai-assistant', 'ui-ai-search-setup', 'ui-form', 'ui-calculator', 'ui-form-field-template', 'ui-email-template', 'ui-font', 'ui-review', 'ui-media-add', 'ui-ai-search-answers'], array_column($projects, 'slug'));
        $this->assertSame([3010, 3020, 3030, 3040, 3045, 3050, 3060, 3070, 3080, 3090, 3100, 3110, 3130], array_column($projects, 'order'));
    }

    // Orders are merged across every bundle contributing projects, and two equal ones leave their sequence to the order the providers happen to be registered in - this bundle's own block is the 3000 GuidedProjectProviderInterface reserves it
    public function testEveryOrderStaysWithinThisBundlesReservedRange(): void
    {
        $orders = array_column($this->createProvider()->getGuidedProjects(), 'order', 'slug');

        foreach ($orders as $slug => $order) {
            $this->assertGreaterThanOrEqual(3000, $order, sprintf('Project "%s" reaches into SiteBundle\'s own 2000 block', $slug));
            $this->assertLessThanOrEqual(3999, $order, sprintf('Project "%s" reaches into SocialBundle\'s own 4000 block', $slug));
        }

        $this->assertSameSize($orders, array_unique($orders), 'Two projects sharing an order leave their sequence to chance');
    }

    // A project offered to someone the screen it opens on turns away is a parcours ending on an access-denied page - GuidedProjectBuilder drops it on "role", so each one has to state the gate its own controller sets
    public function testEveryProjectIsGatedByTheRoleItsOwnScreenNeeds(): void
    {
        $expected = [
            'ui-media' => 'ROLE_EDITOR',
            'ui-site-graphic' => 'ROLE_EDITOR',
            'ui-legal-model' => 'ROLE_EDITOR',
            'ui-ai-assistant' => 'ROLE_ADMIN',
            'ui-form' => 'ROLE_ADMIN',
            'ui-calculator' => 'ROLE_ADMIN',
            'ui-form-field-template' => 'ROLE_ADMIN',
            'ui-email-template' => 'ROLE_ADMIN',
            'ui-font' => 'ROLE_EDITOR',
            'ui-review' => 'ROLE_EDITOR',
            'ui-media-add' => 'ROLE_EDITOR',
            'ui-ai-search-setup' => 'ROLE_ADMIN',
            'ui-ai-search-answers' => 'ROLE_EDITOR',
        ];

        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertSame($expected[$project['slug']], $project['role'] ?? null, sprintf('Project "%s" walks a screen the current user may not reach', $project['slug']));
        }
    }

    public function testEverySlugIsPrefixedWithTheBundleName(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertStringStartsWith('ui-', $project['slug'], 'A slug is unique across every bundle contributing projects');
        }
    }

    public function testEveryProjectCarriesTheUiTranslationDomainAndSteps(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertSame('ui', $project['translation_domain']);
            $this->assertNotEmpty($project['steps']);
        }
    }

    public function testNoStepSetsBothUrlAndHighlight(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $index => $step) {
                $this->assertFalse(
                    isset($step['url']) && isset($step['highlight']),
                    sprintf('Step %d of "%s" sets both url and highlight', $index, $project['slug'])
                );
            }
        }
    }

    // Only the opening step leaves the screen, everything after it walking the one the user has been sent to
    public function testOnlyTheFirstStepOfEachProjectCarriesAnUrl(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $steps = $project['steps'];

            $this->assertArrayHasKey('url', $steps[0], sprintf('Project "%s" does not open on a screen', $project['slug']));

            foreach (array_slice($steps, 1) as $index => $step) {
                $this->assertArrayNotHasKey('url', $step, sprintf('Step %d of "%s" leaves the screen again', $index + 1, $project['slug']));
            }
        }
    }

    public function testProjectsOpenOnTheirOwnCrudIndex(): void
    {
        $controllers = [];
        $this->createProvider($controllers)->getGuidedProjects();

        $this->assertSame(
            ['MediaCrudController', 'SiteGraphicCrudController', 'ConfigCrudController', 'FormCrudController', 'FormCrudController', 'FormFieldTemplateCrudController', 'EmailTemplateCrudController', 'FontCrudController', 'ReviewCrudController', 'MediaCrudController', 'AiSearchAnswerCrudController'],
            array_map(static fn (string $fqcn): string => basename(str_replace('\\', '/', $fqcn)), $controllers)
        );
    }

    // The two screens of this bundle a project opens on that are not CRUD ones, so EasyAdmin's generator never sees them
    public function testTheRouteBasedProjectsOpenOnTheirOwnRoute(): void
    {
        $controllers = [];
        $routes = [];
        $this->createProvider($controllers, $routes)->getGuidedProjects();

        $this->assertSame([LegalModelController::INDEX_ROUTE, AiAssistantController::INDEX_ROUTE], $routes);
    }

    // EasyAdmin names a button `action-` . the action's own name, so a selector naming an action it does not declare highlights nothing
    public function testEveryActionHighlightNamesAnEasyAdminAction(): void
    {
        $names = $this->easyAdminActionNames();

        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $index => $step) {
                if (!isset($step['highlight']) || !str_starts_with($step['highlight'], '.action-')) {
                    continue;
                }

                $this->assertContains(
                    substr($step['highlight'], \strlen('.action-')),
                    $names,
                    sprintf('Step %d of "%s" highlights "%s", an action EasyAdmin does not declare', $index, $project['slug'], $step['highlight'])
                );
            }
        }
    }

    // The save button of a form is `saveAndReturn`, EasyAdmin declaring no action plainly named `save`. Asserted step by step rather than against a frozen list of them: what matters is that no save step points at anything else, a count only saying how many projects there happen to be
    public function testTheSaveStepsHighlightTheSaveAndReturnButton(): void
    {
        $saveSteps = 0;

        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $index => $step) {
                if (!str_ends_with($step['label'], '_save')) {
                    continue;
                }

                ++$saveSteps;
                $this->assertSame('.action-saveAndReturn', $step['highlight'] ?? null, sprintf('Step %d of "%s" does not point at the save button', $index, $project['slug']));
            }
        }

        $this->assertGreaterThan(0, $saveSteps, 'No save step found at all, the assertion above would pass over an empty loop');
    }

    // EasyAdmin's own actions, plus the ones this bundle's controllers declare themselves: ActionFactory names a button "action-" . the action's name either way, so a custom action is just as legitimate a target as a built-in one - what stays caught is a name no one declares at all
    private function easyAdminActionNames(): array
    {
        $names = [];
        foreach (new \ReflectionClass(Action::class)->getConstants() as $name => $value) {
            if (!str_starts_with($name, 'TYPE_')) {
                $names[] = $value;
            }
        }

        foreach (glob(\dirname(__DIR__, 2) . '/src/Controller/Management/*.php') ?: [] as $controller) {
            preg_match_all("/Action::new\(\s*'([^']+)'/", file_get_contents($controller) ?: '', $matches);
            $names = [...$names, ...$matches[1]];
        }

        return $names;
    }

    // A label or description with no translation reads as its own key in the panel
    public function testEveryLabelAndDescriptionIsTranslated(): void
    {
        $translated = $this->translatedKeys();

        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ([$project, ...$project['steps']] as $item) {
                $this->assertContains($item['label'], $translated);
                if (isset($item['description'])) {
                    $this->assertContains($item['description'], $translated);
                }
            }
        }
    }

    private function translatedKeys(): array
    {
        $xliff = new \DOMDocument();
        $xliff->load(\dirname(__DIR__, 2) . '/translations/ui.fr.xlf');

        $keys = [];
        foreach ($xliff->getElementsByTagName('source') as $source) {
            $keys[] = $source->textContent;
        }

        return $keys;
    }
}
