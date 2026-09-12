<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use c975L\ConfigBundle\Controller\Management\DashboardController;
use c975L\ConfigBundle\Security\RolePreview;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

// Starts and stops a role preview (see RolePreview). Outside /management on purpose: previewing as a member closes the back office, and the way back has to stay open. GET links carrying a CSRF token, the back office's user menu offering links only
class RolePreviewController extends AbstractController
{
    public const string CSRF_ID = 'role_preview';

    public function __construct(
        private readonly RolePreview $rolePreview,
    ) {
    }

    // Looks at the site as a lower level - checked against the account's real roles, not isGranted(), which already answers for a level being previewed
    #[Route('/role-preview/{level}', name: 'config_role_preview_start', requirements: ['level' => '[a-z_]+'], methods: ['GET'])]
    public function start(Request $request, string $level): RedirectResponse
    {
        $user = $this->checkedUser($request);
        if (!\array_key_exists($level, $this->rolePreview->availableLevels($user->getRoles()))) {
            throw $this->createAccessDeniedException();
        }

        $this->rolePreview->start($level);

        return $this->redirectBack($request, RolePreview::MEMBER === $level);
    }

    // Back to the account's own roles
    #[Route('/role-preview-stop', name: 'config_role_preview_stop', methods: ['GET'])]
    public function stop(Request $request): RedirectResponse
    {
        $this->checkedUser($request);
        $this->rolePreview->stop();

        return $this->redirectBack($request, false);
    }

    // A signed-in account and a valid token, whatever the previewed level
    private function checkedUser(Request $request): UserInterface
    {
        $user = $this->getUser();
        if (null === $user || !$this->isCsrfTokenValid(self::CSRF_ID, $request->query->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    // The page the link was clicked on, when it belongs to this site. A back-office page goes to the dashboard instead, the screen itself possibly refused to the previewed level - and to the home page for a member, refused the whole back office
    private function redirectBack(Request $request, bool $leavesBackOffice): RedirectResponse
    {
        $referer = (string) $request->headers->get('referer');
        $path = (string) parse_url($referer, \PHP_URL_PATH);
        $isOwnPage = '' !== $path && parse_url($referer, \PHP_URL_HOST) === $request->getHost();

        if ($isOwnPage && !DashboardController::isManagementPath($path)) {
            return $this->redirect($referer);
        }

        return $leavesBackOffice ? $this->redirect($request->getBasePath() . '/') : $this->redirectToRoute('management');
    }
}
