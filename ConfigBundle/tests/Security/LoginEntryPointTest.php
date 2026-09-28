<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Security;

use c975L\ConfigBundle\Security\LoginEntryPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class LoginEntryPointTest extends TestCase
{
    // A route saying its language hands it on to the login form
    public function testTheAskedRouteLanguageIsCarried(): void
    {
        $request = Request::create('/en/account');
        $request->attributes->set('_locale', 'en');

        $this->assertSame('/login?_locale=en', $this->entryPoint()->start($request)->getTargetUrl());
    }

    // A route saying none leads to the bare form, the language then read from the session or the browser
    public function testARouteWithoutLanguageLeadsToTheBareForm(): void
    {
        $this->assertSame('/login', $this->entryPoint()->start(Request::create('/account'))->getTargetUrl());
    }

    // The entry point over a router knowing the login route alone
    private function entryPoint(): LoginEntryPoint
    {
        $routes = new RouteCollection();
        $routes->add('app_login', new Route('/login'));

        return new LoginEntryPoint(new UrlGenerator($routes, new RequestContext()));
    }
}
