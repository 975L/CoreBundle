<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// How to delete one's account, open to anyone: the address a store asks for (Google Play's account deletion link), naming the site, the steps and what is kept, before AccountDeleteController's own page, which needs a signed-in user
class AccountDeletionController extends AbstractController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // The steps and the data erased or kept, then the way to the deletion itself
    #[Route('/account/deletion', name: 'config_account_deletion', methods: ['GET'])]
    public function deletion(): Response
    {
        return $this->render('@c975LConfig/account/deletion.html.twig', [
            'siteName' => (string) $this->configService->get('site-name'),
        ]);
    }
}
