<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management;

use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

// Flips the "email-debug" config from the dashboard rather than from the Config screen, debug mode being switched on for the length of a test and off right after - the sending itself is EmailService's affair, which renders a preview instead of sending as long as the key is on and the user is ROLE_SUPER_ADMIN
class EmailDebugShortcutController extends AbstractController
{
    // EasyAdmin prefixes this with the Dashboard's own route name, giving management_ui_email_debug_toggle
    public const TOGGLE_ROUTE = 'management_ui_email_debug_toggle';

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly EntityManagerInterface $manager,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[AdminRoute(
        path: '/ui/email-debug-toggle',
        name: 'ui_email_debug_toggle',
        options: ['methods' => ['POST']]
    )]
    public function toggle(Request $request): RedirectResponse
    {
        // The config itself is restricted (see configs.json), and only a ROLE_SUPER_ADMIN ever gets a preview instead of a real send: anyone else would turn a switch that does nothing for them
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        $config = $this->configRepository->findOneBySlug('email-debug');
        if (null !== $config && $this->isCsrfTokenValid(self::TOGGLE_ROUTE, $request->request->get('_token'))) {
            $enabled = !$this->configService->getBool($config->getValue());
            $config->setValue($enabled);
            $config->setModification(new \DateTime());

            $this->manager->flush();
            $this->configService->invalidateCache();

            $this->addFlash('success', $this->translator->trans(
                $enabled ? 'flash.email_debug_enabled' : 'flash.email_debug_disabled',
                [],
                'ui',
            ));
        }

        return $this->redirectToRoute('management');
    }
}
