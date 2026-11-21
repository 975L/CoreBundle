<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller;

use c975L\ConfigBundle\Controller\OAuthLoginController;
use c975L\ConfigBundle\Security\OAuthLoginProviderInterface;
use c975L\ConfigBundle\Security\OAuthLoginProviderRegistry;
use c975L\ConfigBundle\Service\OAuthLoginClient;
use c975L\ConfigBundle\Service\OAuthUserResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The two rules of this controller worth testing on their own: where it agrees to send a visitor back to once the provider answered, and how Apple's posted answer is passed on. Reached without a kernel, the alternative being a firewall for a string check.
class OAuthLoginControllerTest extends TestCase
{
    private function relativePath(mixed $path): ?string
    {
        $method = new \ReflectionMethod(OAuthLoginController::class, 'relativePath');

        return $method->invoke(
            new \ReflectionClass(OAuthLoginController::class)->newInstanceWithoutConstructor(),
            $path
        );
    }

    public function testAPathOfThisSiteIsKept(): void
    {
        $this->assertSame('/shop/basket/paid/ABC123/deadbeefdeadbeef', $this->relativePath('/shop/basket/paid/ABC123/deadbeefdeadbeef'));
        $this->assertSame('/', $this->relativePath('/'));
        $this->assertSame('/account/orders?page=2', $this->relativePath('/account/orders?page=2'));
    }

    // An open redirect is a login page handing a visitor to whoever wrote the link they clicked - and "//host" is an absolute url a browser follows off-site just as "https://host" is
    public function testAnythingLeadingOffSiteIsDropped(): void
    {
        $this->assertNull($this->relativePath('//evil.test'));
        $this->assertNull($this->relativePath('https://evil.test'));
        $this->assertNull($this->relativePath('http://evil.test'));
        // Some browsers have read a backslash as a slash, which makes this an off-site host too
        $this->assertNull($this->relativePath('/\evil.test'));
        $this->assertNull($this->relativePath('/orders\..\..'));
        // A browser drops these out of a url, which leaves it with "//evil.test"
        $this->assertNull($this->relativePath("/\t/evil.test"));
        $this->assertNull($this->relativePath("/\n/evil.test"));
        $this->assertNull($this->relativePath("/orders\x00"));
    }

    public function testAnythingThatIsNotAPathIsDropped(): void
    {
        $this->assertNull($this->relativePath('relative/path'));
        $this->assertNull($this->relativePath(''));
        $this->assertNull($this->relativePath(null));
        $this->assertNull($this->relativePath(['/orders']));
    }

    // Apple posts its answer from its own site, which brings no session cookie: passed on as a GET to the same address, without a session being opened - a Request holding none would throw on the first getSession()
    public function testAnAnswerPostedBackIsSentOnAsAGetWithoutTouchingTheSession(): void
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $name, array $parameters): string => '/connect/' . $parameters['provider'] . '/check?' . http_build_query(array_diff_key($parameters, ['provider' => true])));
        $container = new Container();
        $container->set('router', $router);
        $controller = new \ReflectionClass(OAuthLoginController::class)->newInstanceWithoutConstructor();
        $controller->setContainer($container);

        $response = $controller->checkPosted(Request::create('/connect/apple/check', 'POST', ['code' => 'a-code', 'state' => 'a-state', 'user' => '{"name":{}}']), 'apple');

        $this->assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
        $this->assertSame('/connect/apple/check?code=a-code&state=a-state', $response->headers->get('Location'));
        $this->assertSame([], $response->headers->getCookies());
    }

    // A state that is not the one this session sent, or not a string at all, is refused before any code is traded
    #[DataProvider('wrongStates')]
    public function testAWrongStateIsRefusedBeforeTheCodeIsTraded(mixed $state): void
    {
        $provider = $this->createStub(OAuthLoginProviderInterface::class);
        $provider->method('getKey')->willReturn('google');
        $registry = $this->createStub(OAuthLoginProviderRegistry::class);
        $registry->method('get')->willReturn($provider);
        $client = $this->createMock(OAuthLoginClient::class);
        $client->expects($this->never())->method('exchangeCodeForToken');

        $session = new Session(new MockArraySessionStorage());
        $session->set('config_oauth_state_google', 'state-1');
        $request = Request::create('/connect/google/check', 'GET', ['code' => 'a-code', 'state' => $state]);
        $request->setSession($session);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/login');
        $container = new Container();
        $container->set('router', $router);
        $container->set('request_stack', new RequestStack([$request]));

        $controller = new OAuthLoginController($registry, $client, $this->createStub(OAuthUserResolver::class), $this->createStub(TranslatorInterface::class));
        $controller->setContainer($container);
        $response = $controller->check($request, 'google', $this->createStub(Security::class));

        $this->assertSame('/login', $response->headers->get('Location'));
        $this->assertFalse($session->has('config_oauth_state_google'));
    }

    /** @return iterable<string, array{mixed}> */
    public static function wrongStates(): iterable
    {
        yield 'another state' => ['state-2'];
        yield 'a prefix of it' => ['state-'];
        yield 'none' => [null];
    }
}
