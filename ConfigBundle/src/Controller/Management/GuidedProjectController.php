<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller\Management;

use c975L\ConfigBundle\Management\GuidedProjectBuilder;
use c975L\ConfigBundle\Security\Voter\BackOfficeAccessVoter;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class GuidedProjectController extends AbstractController
{
    public function __construct(
        private readonly GuidedProjectBuilder $guidedProjectBuilder,
    ) {
    }

    // Serves one project's steps to the panel (see assets/js/guided-project.js), which knows nothing more than the slug its own browser stored. Called only while a parcours is running, so the mount element sitting on every admin page costs no request at all in normal use. Registered under the Dashboard's own path/name (EasyAdmin prefixes both), giving /management/guided-project/{slug} and management_guided_project_steps - deliberately not under a CRUD's path, where a GET would be swallowed by its own /{entityId} route
    #[AdminRoute(path: '/guided-project/{slug}', name: 'guided_project_steps', options: ['methods' => ['GET']])]
    public function steps(string $slug): JsonResponse
    {
        // Same floor as the dashboard the panel is started from - GuidedProjectBuilder::getProject() still answers null for a project the caller lacks the role of
        $this->denyAccessUnlessGranted(BackOfficeAccessVoter::ACCESS);

        // Null both for a slug no provider declares anymore and for a project the current user lacks the role for - the panel treats the 404 the same either way, dropping the slug and forgetting it
        $project = $this->guidedProjectBuilder->getProject($slug);
        if (null === $project) {
            throw $this->createNotFoundException();
        }

        return new JsonResponse($project);
    }
}
