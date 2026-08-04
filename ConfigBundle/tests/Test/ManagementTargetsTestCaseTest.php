<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Test;

use c975L\ConfigBundle\Controller\Management\ConfigCrudController;
use c975L\ConfigBundle\Controller\Management\GuidedProjectController;
use c975L\ConfigBundle\Controller\Management\HealthCheckController;
use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\ConfigBundle\Test\ManagementTargetsTestCase;

// The case itself, run over entries no bundle here declares: a menu pointing at a plain #[AdminRoute] screen rather than at a CRUD controller (see MenuProviderInterface::getMenus()). ConfigBundle's own ManagementTargetsTest only ever hands it CRUD ones, so nothing else would notice that shape stopping to resolve
class ManagementTargetsTestCaseTest extends ManagementTargetsTestCase
{
    protected function managementProviders(): iterable
    {
        return [$this->createMenuProvider([
            // A CRUD controller, whose index route EasyAdmin resolves on its own
            'config' => ['controller' => ConfigCrudController::class, 'label' => 'label.config', 'translation_domain' => 'config', 'icon' => 'fa fa-cog'],
            // A plain controller naming no action, the #[AdminRoute] sitting on the index() opened by default
            'health-check' => ['controller' => HealthCheckController::class, 'label' => 'label.health_check', 'translation_domain' => 'config', 'icon' => 'fa fa-heart'],
            // A plain controller naming the action to open instead
            'guided-project' => ['controller' => GuidedProjectController::class, 'action' => 'steps', 'label' => 'label.guided_project', 'translation_domain' => 'config', 'icon' => 'fa fa-map'],
        ])];
    }

    // Written in plain PHP rather than stubbed: the case is instantiated by PHPUnit for each of its inherited tests, and this provider only ever returns what it is given
    private function createMenuProvider(array $menus): MenuProviderInterface
    {
        return new class ($menus) implements MenuProviderInterface {
            public function __construct(private readonly array $menus)
            {
            }

            public function getMenuSection(): array
            {
                return ['label' => 'label.management', 'translation_domain' => 'config'];
            }

            public function getMenus(): array
            {
                return $this->menus;
            }

            public function getLinks(): array
            {
                return [];
            }
        };
    }
}
