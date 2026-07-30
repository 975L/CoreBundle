<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\UiBundle\Management\UiGuidedProjectProvider;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class UiGuidedProjectProviderTest extends TestCase
{
    private function createAdminUrlGenerator(array &$controllers = []): AdminUrlGeneratorInterface
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnCallback(function (string $controller) use ($generator, &$controllers) {
            $controllers[] = $controller;

            return $generator;
        });
        $generator->method('setAction')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/management/x');

        return $generator;
    }

    private function createProvider(array &$controllers = []): UiGuidedProjectProvider
    {
        return new UiGuidedProjectProvider($this->createAdminUrlGenerator($controllers));
    }

    // Continues the sequence after ConfigBundle (10-30) and SiteBundle (50-80)
    public function testGetGuidedProjectsContinuesTheOrderSequence(): void
    {
        $projects = $this->createProvider()->getGuidedProjects();

        $this->assertSame(['ui-media', 'ui-form', 'ui-email-template'], array_column($projects, 'slug'));
        $this->assertSame([90, 100, 110], array_column($projects, 'order'));
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
            ['MediaCrudController', 'FormCrudController', 'EmailTemplateCrudController'],
            array_map(static fn (string $fqcn): string => basename(str_replace('\\', '/', $fqcn)), $controllers)
        );
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

    // The save button of a form is `saveAndReturn`, EasyAdmin declaring no action plainly named `save`
    public function testTheSaveStepsHighlightTheSaveAndReturnButton(): void
    {
        $highlights = [];
        foreach ($this->createProvider()->getGuidedProjects() as $project) {
            foreach ($project['steps'] as $step) {
                if (str_ends_with($step['label'], '_save')) {
                    $highlights[] = $step['highlight'];
                }
            }
        }

        $this->assertSame(['.action-saveAndReturn', '.action-saveAndReturn', '.action-saveAndReturn'], $highlights);
    }

    private function easyAdminActionNames(): array
    {
        $names = [];
        foreach ((new \ReflectionClass(Action::class))->getConstants() as $name => $value) {
            if (!str_starts_with($name, 'TYPE_')) {
                $names[] = $value;
            }
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
