<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\ConfigGuidedProjectProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ConfigGuidedProjectProviderTest extends TestCase
{
    private function createAdminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/management/config');

        return $generator;
    }

    private function createUrlGenerator(array &$routes = []): UrlGeneratorInterface
    {
        $generator = $this->createStub(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(
            static function (string $route) use (&$routes): string {
                $routes[] = $route;

                return '/management/' . $route;
            }
        );

        return $generator;
    }

    private function createProvider(array &$routes = []): ConfigGuidedProjectProvider
    {
        // Answers each role key with itself, so a project's own gate is readable back in the assertions
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnArgument(0);

        return new ConfigGuidedProjectProvider($this->createAdminUrlGenerator(), $configService, $this->createUrlGenerator($routes));
    }

    // The "Traduire" action is drawn on one entry of one drawer (ConfigTranslator::TRANSLATABLE), so a step highlighting it lights nothing up wherever the visitor walked - which is what a guided tour must never do
    public function testTheSettingsProjectWalksNoTranslateStep(): void
    {
        $this->assertNotContains('label.guided_step_config_settings_translate', $this->settingsStepLabels());
    }

    /**
     * @return list<string>
     */
    private function settingsStepLabels(): array
    {
        $routes = [];
        foreach ($this->createProvider($routes)->getGuidedProjects() as $project) {
            if ('config-settings' === $project['slug']) {
                return array_column($project['steps'], 'label');
            }
        }

        self::fail('The "config-settings" guided project was not found.');
    }

    // The 1000 block GuidedProjectProviderInterface reserves this bundle, at the step of 10 it states
    public function testGetGuidedProjectsOpensTheOrderSequence(): void
    {
        $projects = $this->createProvider()->getGuidedProjects();

        $this->assertSame(
            ['config-settings', 'config-health-check', 'config-maintenance', 'config-not-found', 'config-url-metadata'],
            array_column($projects, 'slug')
        );
        // 1040 rather than a value after 1050: the missing pages are walked to the redirects, the screen the url metadata has nothing to do with
        $this->assertSame([1010, 1020, 1030, 1040, 1050], array_column($projects, 'order'));
    }

    // A project is offered on a dashboard an editor now reaches, so one walking an admin screen has to say so or its very first step answers a 403
    public function testEveryProjectIsGatedByTheRoleItsOwnScreenNeeds(): void
    {
        $roles = [];
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $roles[$project['slug']] = $project['role'];
        }

        $this->assertSame(
            [
                'config-settings' => 'site-role-admin',
                'config-health-check' => 'site-role-admin',
                'config-maintenance' => 'site-role-admin',
                // The two of the five whose screens answer an editor (see NotFoundCrudController, UrlMetadataCrudController)
                'config-not-found' => 'site-role-editor',
                'config-url-metadata' => 'site-role-editor',
            ],
            $roles,
        );
    }

    public function testEverySlugIsPrefixedWithTheBundleName(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertStringStartsWith('config-', $project['slug'], 'A slug is unique across every bundle contributing projects');
        }
    }

    public function testEveryProjectCarriesTheConfigTranslationDomainAndSteps(): void
    {
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            $this->assertSame('config', $project['translation_domain']);
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

    // EasyAdmin renders a button as `action-<actionName>`, so a highlight guessing at the name (`.action-save`) points at nothing
    public function testEveryHighlightedActionIsAnEasyAdminOne(): void
    {
        $actions = $this->easyAdminActionNames();

        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $index => $step) {
                if (!isset($step['highlight']) || !preg_match('/^\.action-(\w+)$/', $step['highlight'], $matches)) {
                    continue;
                }

                $this->assertContains(
                    $matches[1],
                    $actions,
                    sprintf('Step %d of "%s" highlights an action EasyAdmin does not render', $index, $project['slug'])
                );
            }
        }
    }

    // EasyAdmin's own actions, plus the ones this bundle's controllers declare themselves: ActionFactory names a button "action-" . the action's name either way, so a custom action is just as legitimate a target as a built-in one - what stays caught is a name no one declares at all
    private function easyAdminActionNames(): array
    {
        $constants = new \ReflectionClass(Action::class)->getConstants();

        $names = array_values(array_filter(
            $constants,
            static fn (string $name): bool => !str_starts_with($name, 'TYPE_'),
            ARRAY_FILTER_USE_KEY
        ));

        foreach (glob(\dirname(__DIR__, 2) . '/src/Controller/Management/*.php') ?: [] as $controller) {
            preg_match_all("/Action::new\(\s*'([^']+)'/", file_get_contents($controller) ?: '', $matches);
            $names = [...$names, ...$matches[1]];
        }

        return $names;
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

    public function testTheRouteBasedProjectsOpenOnTheirOwnScreen(): void
    {
        $routes = [];
        $this->createProvider($routes)->getGuidedProjects();

        $this->assertSame(['management_health_check_index', 'management'], $routes);
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
        $xliff->load(\dirname(__DIR__, 2) . '/translations/config.fr.xlf');

        $keys = [];
        foreach ($xliff->getElementsByTagName('source') as $source) {
            $keys[] = $source->textContent;
        }

        return $keys;
    }
}
