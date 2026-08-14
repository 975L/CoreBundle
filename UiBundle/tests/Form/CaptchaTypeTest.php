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
use c975L\UiBundle\Validator\Constraints\Captcha;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CaptchaTypeTest extends TestCase
{
    /**
     * @param array<string, mixed> $configValues
     */
    private function createType(array $configValues): CaptchaType
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnCallback(static fn (string $key) => array_key_exists($key, $configValues));
        $configService->method('get')->willReturnCallback(static fn (string $key) => $configValues[$key] ?? null);

        $requestStack = new RequestStack([new Request()]);

        return new CaptchaType(new CaptchaVerifier(new MockHttpClient(), $configService, $requestStack));
    }

    private const array KEYS = [
        'recaptcha3-site-key' => 'site-key',
        'recaptcha3-secret-key' => 'secret-key',
    ];

    /**
     * @param array<string, mixed> $configValues
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function buildViewVars(array $configValues, array $options = []): array
    {
        $resolver = new OptionsResolver();
        $type = $this->createType($configValues);
        $type->configureOptions($resolver);

        $view = new FormView();
        $type->buildView($view, $this->createStub(FormInterface::class), $resolver->resolve($options));

        return $view->vars;
    }

    // Everything the Stimulus controller needs to fetch a token, and nothing more
    public function testViewCarriesTheSiteKeyAndActionName(): void
    {
        $vars = $this->buildViewVars(self::KEYS, ['action_name' => 'contact']);

        $this->assertTrue($vars['enabled']);
        $this->assertSame('site-key', $vars['site_key']);
        $this->assertSame('contact', $vars['action_name']);
    }

    // Without keys the widget renders a bare hidden input - no controller, no request to Google
    public function testViewIsDisabledWhenNoKeysAreConfigured(): void
    {
        $vars = $this->buildViewVars([]);

        $this->assertFalse($vars['enabled']);
        $this->assertNull($vars['site_key']);
    }

    public function testActionNameDefaultsToForm(): void
    {
        $this->assertSame('form', $this->buildViewVars(self::KEYS)['action_name']);
    }

    // The token is machine-written: no label, no asterisk, nothing mapped onto the underlying data
    public function testFieldIsUnmappedUnlabelledAndNotRequired(): void
    {
        $resolver = new OptionsResolver();
        $this->createType(self::KEYS)->configureOptions($resolver);
        $options = $resolver->resolve();

        $this->assertFalse($options['mapped']);
        $this->assertFalse($options['required']);
        $this->assertFalse($options['label']);
        $this->assertEquals([new Captcha()], $options['constraints']);
    }

    public function testItRendersAsAHiddenField(): void
    {
        $this->assertSame(HiddenType::class, $this->createType(self::KEYS)->getParent());
        $this->assertSame('c975l_ui_captcha', $this->createType(self::KEYS)->getBlockPrefix());
    }
}
