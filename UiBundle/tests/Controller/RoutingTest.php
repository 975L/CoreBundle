<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller;

use c975L\UiBundle\Controller\FormController;
use c975L\UiBundle\Controller\ReviewController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpKernel\Attribute\Cache;

class RoutingTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string, string}>
     */
    public static function publicRoutes(): iterable
    {
        yield 'form submit' => [FormController::class, 'ui_form_submit', 'submit'];
        yield 'review new' => [ReviewController::class, 'ui_review_new', 'new'];
    }

    /**
     * A route attribute binds to whichever method follows it, so a private helper extracted in between silently steals it - and the controller is then not callable at all. Loaded here as the router itself would.
     *
     * @param class-string $controller
     */
    #[DataProvider('publicRoutes')]
    public function testRouteIsBoundToItsPublicAction(string $controller, string $routeName, string $method): void
    {
        $routes = new AttributeRouteControllerLoader()->load($controller);
        $route = $routes->get($routeName);

        $this->assertNotNull($route, sprintf('Route "%s" is not declared by "%s".', $routeName, $controller));
        $this->assertSame($controller . '::' . $method, $route->getDefault('_controller'));
    }

    /**
     * Every route of these controllers points at a public method, whatever its name.
     *
     * @param class-string $controller
     */
    #[DataProvider('controllers')]
    public function testEveryRouteTargetsAPublicMethod(string $controller): void
    {
        $routes = new AttributeRouteControllerLoader()->load($controller);

        $this->assertGreaterThan(0, $routes->count(), sprintf('"%s" declares no route at all.', $controller));

        foreach ($routes as $name => $route) {
            [, $method] = explode('::', (string) $route->getDefault('_controller'), 2);
            $this->assertTrue(
                new \ReflectionMethod($controller, $method)->isPublic(),
                sprintf('Route "%s" points at "%s::%s()", which is not public.', $name, $controller, $method)
            );
        }
    }

    /**
     * The review page carries a session-bound honeypot and a csrf token: its no-store headers must sit on the action itself, not on a helper the router never calls.
     */
    public function testReviewActionKeepsItsCacheAttribute(): void
    {
        $attributes = new \ReflectionMethod(ReviewController::class, 'new')->getAttributes(Cache::class);

        $this->assertCount(1, $attributes);

        $cache = $attributes[0]->newInstance();
        $this->assertSame(0, $cache->maxage);
        $this->assertFalse($cache->public);
        $this->assertTrue($cache->mustRevalidate);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function controllers(): iterable
    {
        yield 'FormController' => [FormController::class];
        yield 'ReviewController' => [ReviewController::class];
    }
}
