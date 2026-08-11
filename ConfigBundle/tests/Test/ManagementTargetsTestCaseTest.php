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
use c975L\ConfigBundle\Management\LinkableRouteProviderInterface;
use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\ConfigBundle\Test\ManagementTargetsTestCase;

// The case itself, run over entries no bundle here declares: a menu pointing at a plain #[AdminRoute] screen rather than at a CRUD controller (see MenuProviderInterface::getMenus()), and a linkable route standing for one row of a bundle's own data (see LinkableRouteProviderInterface). ConfigBundle's own ManagementTargetsTest only ever hands it CRUD menus and a plain route name, so nothing else would notice either shape stopping to resolve
class ManagementTargetsTestCaseTest extends ManagementTargetsTestCase
{
    protected function managementProviders(): iterable
    {
        return [
            $this->createMenuProvider([
                // A CRUD controller, whose index route EasyAdmin resolves on its own
                'config' => ['controller' => ConfigCrudController::class, 'label' => 'label.config', 'translation_domain' => 'config', 'icon' => 'fa fa-cog'],
                // A plain controller naming no action, the #[AdminRoute] sitting on the index() opened by default
                'health-check' => ['controller' => HealthCheckController::class, 'label' => 'label.health_check', 'translation_domain' => 'config', 'icon' => 'fa fa-heart'],
                // A plain controller naming the action to open instead
                'guided-project' => ['controller' => GuidedProjectController::class, 'action' => 'steps', 'label' => 'label.guided_project', 'translation_domain' => 'config', 'icon' => 'fa fa-map'],
            ]),
            // A key standing for a row rather than for a route: the case has to check the 'route' this entry names, its key being no route name at all, and to accept that key because it carries a literal in front of its id
            $this->createLinkableRouteProvider(['dashboard.7' => [
                'label' => 'Paysages',
                'translation_domain' => false,
                'route' => 'management',
                'params' => ['category' => 'paysages'],
            ]]),
        ];
    }

    // Written in plain PHP rather than stubbed: the case is instantiated by PHPUnit for each of its inherited tests, and this provider only ever returns what it is given
    private function createMenuProvider(array $menus): MenuProviderInterface
    {
        return new readonly class ($menus) implements MenuProviderInterface {
            public function __construct(private array $menus)
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

    // Same reason as above: the case reads what this returns, nothing more
    private function createLinkableRouteProvider(array $routes): LinkableRouteProviderInterface
    {
        return new readonly class ($routes) implements LinkableRouteProviderInterface {
            public function __construct(private array $routes)
            {
            }

            public function getLinkableRoutes(): array
            {
                return $this->routes;
            }
        };
    }
}
