<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Form\CaptchaType;
use c975L\UiBundle\Service\CaptchaVerifier;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormRendererInterface;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

// Actually renders the widget rather than reading the template as text: overriding the block at the "c975l_ui_captcha" prefix bypasses "hidden_widget", so a missing "type" default silently produces a visible text input carrying the token - the sort of thing no unit test on CaptchaType would ever notice
class CaptchaWidgetRenderTest extends FormIntegrationTestCase
{
    private const array KEYS = [
        'recaptcha3-site-key' => 'site-key',
        'recaptcha3-secret-key' => 'secret-key',
    ];

    /** @var array<string, mixed> */
    private array $configValues = self::KEYS;

    #[\Override]
    protected function getTypes(): array
    {
        $configValues = $this->configValues;

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnCallback(static fn (string $key) => array_key_exists($key, $configValues));
        $configService->method('get')->willReturnCallback(static fn (string $key) => $configValues[$key] ?? null);

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        return [new CaptchaType(new CaptchaVerifier(new MockHttpClient(), $configService, $requestStack))];
    }

    private function renderWidget(): string
    {
        // getTypes() is consumed by setUp(), so a test wanting other config re-runs it
        parent::setUp();

        $formDirectory = \dirname(new \ReflectionClass(FormExtension::class)->getFileName(), 2) . '/Resources/views/Form';
        $twig = new Environment(new FilesystemLoader([
            $formDirectory,
            \dirname(__DIR__, 2) . '/templates',
        ]));
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addExtension(new FormExtension());

        $theme = 'form/captcha_theme.html.twig';
        $engine = new TwigRendererEngine(['form_div_layout.html.twig', $theme], $twig);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRendererInterface::class => static fn (): FormRenderer => new FormRenderer($engine),
        ]));

        $form = $this->factory->create(CaptchaType::class, null, ['action_name' => 'contact']);

        return $twig->getRuntime(FormRendererInterface::class)->searchAndRenderBlock($form->createView(), 'widget');
    }

    public function testItRendersAHiddenInputCarryingTheControllerAndItsValues(): void
    {
        $html = $this->renderWidget();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringNotContainsString('type="text"', $html);
        $this->assertStringContainsString('data-controller="captcha"', $html);
        $this->assertStringContainsString('data-captcha-site-key-value="site-key"', $html);
        $this->assertStringContainsString('data-captcha-action-value="contact"', $html);
    }

    // No keys: still a hidden input, but nothing that would make the browser talk to Google
    public function testItRendersABareHiddenInputWhenDisabled(): void
    {
        $this->configValues = [];
        $html = $this->renderWidget();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringNotContainsString('data-controller', $html);
        $this->assertStringNotContainsString('site-key', $html);
    }
}
