<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Fixtures;

use c975L\UiBundle\c975LUiBundle;
use Symfony\Bridge\Twig\Extension\HtmlSanitizerExtension;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Environment;

// The bundle's two sanitizers as FrameworkBundle really builds them: its configuration tree and extension decide what an empty list or a "*" comes to mean, which a hand-built HtmlSanitizerConfig would only pretend to know. Booted once per run and shared, what the template tests read through their own bare Twig environment
final class HtmlSanitizerKernel extends Kernel
{
    private static ?self $shared = null;

    // $siteConfig is what a site's own config/packages says under framework.html_sanitizer, loaded after the bundle's prepend as in a real site
    public function __construct(string $environment = 'test', bool $debug = false, private readonly array $siteConfig = [])
    {
        parent::__construct($environment, $debug);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'router' => ['enabled' => false, 'resource' => 'unused'],
                'php_errors' => ['log' => false],
                ...([] === $this->siteConfig ? [] : ['html_sanitizer' => $this->siteConfig]),
            ]);
            $container->loadFromExtension('twig', ['default_path' => __DIR__]);
        });
    }

    // The way c975LUiBundle::prependExtension() does it (c975LUiBundleTest holds it to this very array), ahead of the site's own config
    protected function build(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', ['html_sanitizer' => ['sanitizers' => c975LUiBundle::HTML_SANITIZERS]]);
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/c975l-ui-sanitizer-kernel/' . getmypid() . '/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir();
    }

    // The sanitizer of that name, for a test asserting on what it lets through
    public static function sanitizer(string $name): HtmlSanitizerInterface
    {
        return self::instance()->getContainer()->get('test.service_container')->get('html_sanitizer.sanitizer.' . $name);
    }

    // Gives a test's own bare Twig environment the same "sanitize_html" filter a site has
    public static function register(Environment $twig): Environment
    {
        $twig->addExtension(self::instance()->getContainer()->get('test.service_container')->get('twig')->getExtension(HtmlSanitizerExtension::class));

        return $twig;
    }

    private static function instance(): self
    {
        if (null === self::$shared) {
            self::$shared = new self('test', false);
            self::$shared->boot();
        }

        return self::$shared;
    }
}
