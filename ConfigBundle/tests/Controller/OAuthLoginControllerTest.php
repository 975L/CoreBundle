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
use c975L\ConfigBundle\Model\OAuthIdentity;
use c975L\ConfigBundle\Model\OAuthResolution;
use c975L\ConfigBundle\Security\OAuthLoginProviderInterface;
use c975L\ConfigBundle\Security\OAuthLoginProviderRegistry;
use c975L\ConfigBundle\Service\OAuthLoginClient;
use c975L\ConfigBundle\Service\OAuthUserResolver;
use c975L\UiBundle\Entity\FormField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
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

    // A controller over a provider vouching for visitor@example.test, a router naming each route by its name, and the resolver given
    private function createController(Request $request, OAuthUserResolver $resolver): OAuthLoginController
    {
        $provider = $this->createStub(OAuthLoginProviderInterface::class);
        $provider->method('getKey')->willReturn('google');
        $provider->method('fetchIdentity')->willReturn(new OAuthIdentity('visitor@example.test', true));
        $registry = $this->createStub(OAuthLoginProviderRegistry::class);
        $registry->method('get')->willReturn($provider);
        $client = $this->createStub(OAuthLoginClient::class);
        $client->method('exchangeCodeForToken')->willReturn(['id_token' => 'a-token']);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $name): string => '/' . $name);
        $container = new Container();
        $container->set('router', $router);
        $container->set('request_stack', new RequestStack([$request]));

        $controller = new OAuthLoginController($registry, $client, $resolver, $this->createStub(TranslatorInterface::class));
        $controller->setContainer($container);

        return $controller;
    }

    // An account held back for the application's questions is created nowhere yet: the vouched address waits in the session and the visitor is sent to the questions
    public function testAPendingSignUpSendsToTheQuestions(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('config_oauth_state_google', 'state-1');
        $request = Request::create('/connect/google/check', 'GET', ['code' => 'a-code', 'state' => 'state-1']);
        $request->setSession($session);
        $resolver = $this->createStub(OAuthUserResolver::class);
        $resolver->method('resolve')->willReturn(OAuthResolution::refused(OAuthResolution::REASON_SIGN_UP_PENDING));

        $response = $this->createController($request, $resolver)->check($request, 'google', $this->createStub(Security::class));

        $this->assertSame('/config_oauth_sign_up', $response->headers->get('Location'));
        $this->assertSame('visitor@example.test', $session->get('config_oauth_sign_up_google')['email'] ?? null);
    }

    // The questions answer no one who did not come from the provider, nor someone who waited too long
    #[DataProvider('missingSignUps')]
    public function testTheQuestionsNeedAnAddressJustVouchedFor(?array $pending): void
    {
        $session = new Session(new MockArraySessionStorage());
        if (null !== $pending) {
            $session->set('config_oauth_sign_up_google', $pending);
        }
        $request = Request::create('/connect/google/sign-up');
        $request->setSession($session);
        $resolver = $this->createMock(OAuthUserResolver::class);
        $resolver->method('signUpFields')->willReturn([new FormField()->setName('birthDate')]);
        $resolver->expects($this->never())->method('signUp');

        $response = $this->createController($request, $resolver)->signUp($request, 'google', $this->createStub(Security::class));

        $this->assertSame('/app_login', $response->headers->get('Location'));
    }

    /** @return iterable<string, array{?array<string, mixed>}> */
    public static function missingSignUps(): iterable
    {
        yield 'none' => [null];
        yield 'waited too long' => [['email' => 'visitor@example.test', 'at' => time() - 3600]];
    }

    // Answered and turned down by the application, the visitor goes where it says, the address no longer waiting
    public function testATurnedDownSignUpSendsWhereTheApplicationSays(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('config_oauth_sign_up_google', ['email' => 'visitor@example.test', 'at' => time()]);
        $request = Request::create('/connect/google/sign-up', 'POST');
        $request->setSession($session);
        $resolver = $this->createMock(OAuthUserResolver::class);
        $resolver->method('signUpFields')->willReturn([new FormField()->setName('birthDate')]);
        $resolver->expects($this->once())->method('signUp')
            ->with(new OAuthIdentity('visitor@example.test', true), ['birthDate' => '2020-01-01'])
            ->willReturn(OAuthResolution::redirected('/item-1'));

        $form = $this->createStub(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(['birthDate' => '2020-01-01']);
        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $controller = $this->createController($request, $resolver);
        $container = new Container();
        $container->set('form.factory', $formFactory);
        $container->set('request_stack', new RequestStack([$request]));
        $controller->setContainer($container);

        $response = $controller->signUp($request, 'google', $this->createStub(Security::class));

        $this->assertSame('/item-1', $response->headers->get('Location'));
        $this->assertFalse($session->has('config_oauth_sign_up_google'));
    }
}
