<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\BlockLocationProviderInterface;
use c975L\UiBundle\Controller\Management\LegalModelController;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockLocationRegistry;
use c975L\UiBundle\Repository\BlockRepository;
use c975L\UiBundle\Service\BlockCacheInvalidator;
use c975L\UiBundle\Service\LegalModelCatalog;
use c975L\UiBundle\Service\LegalModelCustomizer;
use c975L\UiBundle\Service\LegalModelPlaceholders;
use c975L\UiBundle\Service\LegalModelRenderer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class LegalModelControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private const MODEL = 'france/cookies';

    // Same shape as a shipped model: a tagged loose <div>, a plain <section>, and one holding two <h3>
    private const TEMPLATE = '<div class="legal"><div data-legal-id="intro">Intro</div>'
        . '<section data-legal-id="one"><h2>One</h2><div>Body one</div></section>'
        . '<section data-legal-id="two"><h2>Two</h2><div>Lead two</div>'
        . '<h3 data-legal-id="two.a">A</h3><div>Body A</div><h3 data-legal-id="two.b">B</h3><div>Body B</div></section></div>';

    private function createCustomizer(): LegalModelCustomizer
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('Acme');

        $twig = new Environment(new ArrayLoader([
            '@c975LUi/models/' . self::MODEL . '.fr.html.twig' => self::TEMPLATE,
        ]));

        return new LegalModelCustomizer(new LegalModelRenderer($twig, new LegalModelPlaceholders($config), new LegalModelCatalog()));
    }

    private function createBlock(array $data, string $kind = 'legal_model', int $id = 1): Block
    {
        $block = new Block();
        $block->setKind($kind);
        $block->setData($data);
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, $id);

        return $block;
    }

    // Stands in for SiteBundle's own provider: the screen lists blocks, whoever owns them says where they sit
    private function createLocationRegistry(): BlockLocationRegistry
    {
        $registry = new BlockLocationRegistry();

        $provider = $this->createStub(BlockLocationProviderInterface::class);
        $provider->method('getLocations')->willReturnCallback(
            static fn (array $blocks): array => array_reduce(
                $blocks,
                static function (array $carry, Block $block): array {
                    $carry[$block->getId()] = ['label' => 'Cookies', 'url' => 'https://example.com/pages/cookies', 'published' => true];

                    return $carry;
                },
                [],
            )
        );
        $registry->addProvider($provider);

        return $registry;
    }

    private function createForm(bool $submitted, bool $valid, array $data = []): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('handleRequest')->willReturn($form);
        $form->method('isSubmitted')->willReturn($submitted);
        $form->method('isValid')->willReturn($valid);
        $form->method('getData')->willReturn($data);
        $form->method('createView')->willReturn(new FormView());

        return $form;
    }

    // The two screens render through a real Twig printing only what is asserted, so the parameters the
    // controller builds are checked rather than the shipped templates' markup
    private function createTwig(): Environment
    {
        return new Environment(new ArrayLoader([
            '@c975LUi/management/legal_model_index.html.twig' => '{{ rows|length }}|{{ rows[0].label }}|{{ rows[0].hidden }}|{{ rows[0].overridden }}|{{ rows[0].extra }}|{{ rows[0].drifted }}|{{ rows[0].location.label }}|{{ locale }}|{{ rows[0].known ? \'known\' : \'unknown\' }}',
            '@c975LUi/management/legal_model_customize.html.twig' => '{{ label }}|{{ tree|length }}|{{ tree[2].children|length }}|{{ tree[2].children[0].unit.id }}|{{ tree[2].children[0].index }}|{{ placeholders|length }}',
        ]));
    }

    private function createController(
        array $blocks,
        bool $granted = true,
        ?FormInterface $form = null,
        ?EntityManagerInterface $manager = null,
    ): LegalModelController {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_EDITOR');

        $repository = $this->createStub(BlockRepository::class);
        $repository->method('findByKind')->willReturn($blocks);

        $placeholdersConfig = $this->createStub(ConfigServiceInterface::class);
        $placeholdersConfig->method('get')->willReturn('Acme');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        $controller = new LegalModelController(
            $repository,
            $this->createLocationRegistry(),
            $this->createCustomizer(),
            new LegalModelCatalog(),
            new LegalModelPlaceholders($placeholdersConfig),
            $this->createStub(BlockCacheInvalidator::class),
            $manager ?? $this->createStub(EntityManagerInterface::class),
            $config,
            $translator,
        );

        $formFactory = $this->createStub(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form ?? $this->createForm(false, false));

        [$requestStack, $this->session] = $this->createRequestStackWithSession();

        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker($granted),
            'twig' => $this->createTwig(),
            'form.factory' => $formFactory,
            'request_stack' => $requestStack,
            'router' => $this->createRouter('/management/ui/legal-models/1'),
        ]));

        return $controller;
    }

    private \Symfony\Component\HttpFoundation\Session\Session $session;

    public function testIndexDeniesAccessWithoutTheEditorRole(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createController([], false)->index(new Request());
    }

    public function testIndexCountsWhatEachDocumentCustomized(): void
    {
        $block = $this->createBlock([
            'model' => self::MODEL,
            'customization' => [
                'hidden' => ['one'],
                'overrides' => ['two' => ['title' => 'Ours', 'content' => '<p>Ours</p>', 'baseHash' => 'stale', 'baseLocale' => 'fr']],
                'extra' => [['id' => 'x1', 'parent' => '', 'title' => 'Mine', 'content' => '<p>Mine</p>']],
            ],
        ]);
        $controller = $this->createController([$block]);

        // The screen shows the model in the request's own locale, so the template gets it too
        $request = new Request();
        $request->setLocale('fr');

        $response = $controller->index($request);

        // One row, the model's translation key, then hidden/overridden/extra counts, then the drifted one
        $this->assertSame('1|label.cookies_policy|1|1|1|1|Cookies|fr|known', $response->getContent());
    }

    // A block pointing at a model the bundle does not ship still gets a row, labelled by its raw identifier - and flagged, customize() 404ing on exactly that block: the row must not offer a link into a screen that cannot open
    public function testIndexLabelsAnUnknownModelByItsIdentifierAndFlagsIt(): void
    {
        $controller = $this->createController([$this->createBlock(['model' => 'elsewhere/invented'])]);

        $content = (string) $controller->index(new Request())->getContent();

        $this->assertStringStartsWith('1|elsewhere/invented|0|0|0|0', $content);
        $this->assertStringEndsWith('|unknown', $content);
    }

    // The other half of that same guard, read out of the shipped template: the flag above is only worth computing if the "Customize" link is actually behind it
    public function testTheShippedIndexOnlyLinksAKnownModel(): void
    {
        $template = (string) file_get_contents(\dirname(__DIR__, 3) . '/templates/management/legal_model_index.html.twig');

        $this->assertMatchesRegularExpression(
            '/\{% if row\.known %\}\s*<a href="\{\{ path\(\'' . LegalModelController::CUSTOMIZE_ROUTE . '\'/',
            $template
        );
    }

    public function testCustomizeDeniesAccessWithoutTheEditorRole(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createController([], false)->customize($this->createBlock(['model' => self::MODEL]), new Request());
    }

    public function testCustomizeRejectsABlockOfAnotherKind(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController([])->customize($this->createBlock(['model' => self::MODEL], 'article'), new Request());
    }

    public function testCustomizeRejectsAModelTheBundleDoesNotShip(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController([])->customize($this->createBlock(['model' => 'elsewhere/invented']), new Request());
    }

    // Sub-sections are nested inside their own section's card, which is what scopes the drag and drop
    public function testCustomizeNestsSubSectionsUnderTheirSection(): void
    {
        $response = $this->createController([])->customize($this->createBlock(['model' => self::MODEL]), new Request());

        // Three top-level nodes (intro, one, two), the last holding two.a and two.b, whose row indices follow
        $this->assertSame('label.cookies_policy|3|2|two.a|3|' . \count((new LegalModelPlaceholders($this->createStub(ConfigServiceInterface::class)))->slugs()), $response->getContent());
    }

    public function testCustomizeStoresTheDeltaAndRedirects(): void
    {
        $block = $this->createBlock(['model' => self::MODEL, 'latestUpdate' => '2026-01-01']);
        $form = $this->createForm(true, true, [
            'units' => [
                ['id' => 'intro', 'position' => 0, 'title' => '', 'content' => 'Intro'],
                ['id' => 'one', 'position' => 1, 'hidden' => true, 'title' => 'One', 'content' => '<div>Body one</div>'],
                ['id' => 'two', 'position' => 2, 'title' => 'Two', 'content' => '<p>Rewritten lead</p>'],
            ],
            'extra' => [],
        ]);

        $response = $this->createController([], true, $form)->customize($block, new Request());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/management/ui/legal-models/1', $response->getTargetUrl());
        $this->assertSame(['one'], $block->getData()['customization']['hidden']);
        $this->assertSame('<p>Rewritten lead</p>', $block->getData()['customization']['overrides']['two']['content']);
        // Untouched rows produce no override at all, which is what keeps them on the updatable path
        $this->assertArrayNotHasKey('intro', $block->getData()['customization']['overrides']);
        $this->assertSame('2026-01-01', $block->getData()['latestUpdate']);
        $this->assertSame(['flash.legal_model_customized'], $this->session->getFlashBag()->get('success'));
    }

    // Saving the screen untouched must leave the block byte-for-byte as it was
    public function testCustomizeStoresNothingWhenNothingChanged(): void
    {
        $block = $this->createBlock(['model' => self::MODEL]);
        $form = $this->createForm(true, true, [
            'units' => [
                ['id' => 'intro', 'position' => 0, 'title' => '', 'content' => 'Intro'],
                ['id' => 'one', 'position' => 1, 'title' => 'One', 'content' => '<div>Body one</div>'],
            ],
            'extra' => [],
        ]);

        $this->createController([], true, $form)->customize($block, new Request());

        $this->assertSame(['model' => self::MODEL], $block->getData());
    }
}
