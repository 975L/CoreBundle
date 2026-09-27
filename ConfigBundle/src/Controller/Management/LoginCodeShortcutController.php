<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller\Management;

use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Security\LoginCode;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginCodeShortcutController extends AbstractController
{
    // EasyAdmin prefixes this with the Dashboard's own route name, giving management_config_login_code_toggle
    public const TOGGLE_ROUTE = 'management_config_login_code_toggle';

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly EntityManagerInterface $manager,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Flips the "login-admin-code" config value; the code itself is asked by LoginCodeSubscriber
    #[AdminRoute(
        path: '/config/login-code-toggle',
        name: 'config_login_code_toggle',
        options: ['methods' => ['POST']]
    )]
    public function toggle(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        $config = $this->configRepository->findOneBySlug(LoginCode::CONFIG);
        if (null !== $config && $this->isCsrfTokenValid(self::TOGGLE_ROUTE, $request->request->get('_token'))) {
            $enabled = !$this->configService->getBool($config->getValue());
            $config->setValue($enabled);
            $config->setModification(new \DateTime());
            $this->manager->flush();
            $this->configService->invalidateCache();

            $this->addFlash('success', $this->translator->trans($enabled ? 'flash.login_code_enabled' : 'flash.login_code_disabled', [], 'config'));
        }

        return $this->redirectToRoute('management');
    }
}
