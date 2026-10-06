<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Controller\Management\GuidedProjectController;
use c975L\ConfigBundle\Management\GuidedProjectBuilder;
use c975L\ConfigBundle\Security\Voter\BackOfficeAccessVoter;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

class GuidedProjectControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private const array PROJECT = [
        'slug' => 'creer-page',
        'label' => 'Créer une page',
        'description' => '',
        'steps' => [['label' => 'Ouvrir la liste des pages', 'description' => '', 'url' => '/management/page', 'highlight' => null]],
    ];

    // What the page template was handed, filled in by the stubbed Twig
    private array $rendered = [];

    private function createController(?array $project, bool $granted = true): GuidedProjectController
    {
        $guidedProjectBuilder = $this->createStub(GuidedProjectBuilder::class);
        $guidedProjectBuilder->method('getProject')->willReturn($project);
        $guidedProjectBuilder->method('getProjectsByBundle')->willReturn(['SiteBundle' => [self::PROJECT]]);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([['site-name', 'Mon site']]);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(function (string $template, array $parameters = []): string {
            $this->rendered = ['template' => $template, 'parameters' => $parameters];

            return '<html></html>';
        });

        $controller = new GuidedProjectController($guidedProjectBuilder, $configService);
        $controller->setContainer($this->createContainer([
            // Grants the back-office floor and nothing else, the panel following a parcours from any screen the user may open - the project's own role is answered by GuidedProjectBuilder::getProject(), not here
            'security.authorization_checker' => $granted
                ? $this->createAuthorizationCheckerFor(BackOfficeAccessVoter::ACCESS)
                : $this->createAuthorizationChecker(false),
            'twig' => $twig,
        ]));

        return $controller;
    }

    // The page lists the projects bundle by bundle, the application's own section titled with the site's name
    public function testIndexRendersTheProjectsGroupedByBundle(): void
    {
        $response = $this->createController(null)->index();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('@c975LConfig/management/guided_projects.html.twig', $this->rendered['template']);
        $this->assertSame(['SiteBundle' => [self::PROJECT]], $this->rendered['parameters']['groups']);
        $this->assertSame('Mon site', $this->rendered['parameters']['siteName']);
    }

    public function testIndexDeniesAccessBelowTheBackOfficeFloor(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createController(null, false)->index();
    }

    public function testStepsReturnsTheProjectAsJson(): void
    {
        $response = $this->createController(self::PROJECT)->steps('creer-page');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::PROJECT, json_decode($response->getContent(), true));
    }

    // The slug comes from the browser's own storage: a bundle uninstalled since leaves one behind, and the panel drops it on the 404 rather than retrying it on every admin page
    public function testStepsThrowsNotFoundForAnUnknownSlug(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->createController(null)->steps('gone-with-its-bundle');
    }

    public function testStepsDeniesAccessWithoutTheAdminRole(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createController(self::PROJECT, false)->steps('creer-page');
    }
}
