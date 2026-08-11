<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\LegalModelCatalog;
use c975L\UiBundle\Service\LegalModelPlaceholders;
use c975L\UiBundle\Service\LegalModelRenderer;
use c975L\UiBundle\Twig\LegalModelExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class LegalModelExtensionTest extends TestCase
{
    private const string MODEL = 'france/cookies';

    private function createExtension(?string $locale): LegalModelExtension
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturnCallback(
            static fn (string $slug): ?string => 'site-name' === $slug ? 'Acme' : null
        );

        $placeholders = new LegalModelPlaceholders($config);
        $twig = new Environment(new ArrayLoader([
            '@c975LUi/models/' . self::MODEL . '.fr.html.twig' => '<div class="legal"><section data-legal-id="one"><h2>Un</h2><div>%site-name%</div></section></div>',
            '@c975LUi/models/' . self::MODEL . '.en.html.twig' => '<div class="legal"><section data-legal-id="one"><h2>One</h2><div>%site-name%</div></section></div>',
        ]));

        $requestStack = new RequestStack();
        if (null !== $locale) {
            $request = new Request();
            $request->setLocale($locale);
            $requestStack->push($request);
        }

        return new LegalModelExtension(
            new LegalModelRenderer($twig, $placeholders, new LegalModelCatalog()),
            $placeholders,
            $requestStack,
        );
    }

    private function createBlock(array $data): Block
    {
        $block = new Block();
        $block->setKind('legal_model');
        $block->setData($data);

        return $block;
    }

    public function testGetFunctionsExposesEveryFunctionAsHtmlSafe(): void
    {
        $functions = $this->createExtension('fr')->getFunctions();
        $names = array_map(static fn ($function): string => $function->getName(), $functions);

        $this->assertSame(['legal_var', 'legal_model', 'legal_model_html'], $names);
        foreach ($functions as $function) {
            $this->assertSame(['html'], $function->getSafe(new \Twig\Node\TextNode('', 0)));
        }
    }

    // What an app rendering a legal document from its own template gets, with no block anywhere
    public function testLegalModelHtmlRendersAModelWithoutABlock(): void
    {
        $html = $this->createExtension('en')->legalModelHtml(self::MODEL);

        $this->assertStringContainsString('<h2>One</h2>', $html);
    }

    // An explicit locale wins over the request's, for a document rendered outside any request (a cron, a PDF)
    public function testLegalModelHtmlHonoursAnExplicitLocale(): void
    {
        $html = $this->createExtension('en')->legalModelHtml(self::MODEL, null, [], 'fr');

        $this->assertStringContainsString('<h2>Un</h2>', $html);
    }

    public function testLegalVarReturnsTheConfigValue(): void
    {
        $this->assertSame('Acme', $this->createExtension('fr')->legalVar('site-name'));
    }

    public function testLegalModelRendersTheBlocksModelInTheRequestLocale(): void
    {
        $html = $this->createExtension('en')->legalModel($this->createBlock(['model' => self::MODEL]));

        $this->assertStringContainsString('<h2>One</h2>', $html);
        $this->assertStringContainsString('Acme', $html);
    }

    // A console command or a request-less context still renders, in the locale the models are authored in
    public function testLegalModelFallsBackOnTheAuthoringLocaleWithoutARequest(): void
    {
        $html = $this->createExtension(null)->legalModel($this->createBlock(['model' => self::MODEL]));

        $this->assertStringContainsString('<h2>Un</h2>', $html);
    }

    public function testLegalModelAppliesTheBlocksOwnCustomization(): void
    {
        $block = $this->createBlock([
            'model' => self::MODEL,
            'customization' => ['overrides' => ['one' => ['title' => 'Ours', 'content' => '<p>Our text</p>']]],
        ]);

        $html = $this->createExtension('fr')->legalModel($block);

        $this->assertStringContainsString('<h2>Ours</h2>', $html);
        $this->assertStringContainsString('<p>Our text</p>', $html);
    }

    // A block saved before a model was picked, or pointing at one the bundle does not ship, renders nothing
    public function testLegalModelRendersNothingWithoutAKnownModel(): void
    {
        $this->assertSame('', $this->createExtension('fr')->legalModel($this->createBlock([])));
        $this->assertSame('', $this->createExtension('fr')->legalModel($this->createBlock(['model' => 'elsewhere/invented'])));
    }
}
