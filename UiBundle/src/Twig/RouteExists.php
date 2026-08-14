<?php

/*
 * (c) 2019: 975L <contact@975l.com>
 * (c) 2019: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use Symfony\Component\Routing\RouterInterface;
use Twig\Attribute\AsTwigFunction;

class RouteExists
{
    public function __construct(
        // Stores Router.
        private readonly RouterInterface $router,
    ) {
    }

    #[AsTwigFunction('route_exists')]
    public function routeExists($route)
    {
        return $this->router->getRouteCollection()->get($route);
    }
}
