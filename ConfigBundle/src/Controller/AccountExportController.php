<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use c975L\ConfigBundle\Account\AccountDataCollector;
use c975L\ConfigBundle\Contract\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// The member's data as one JSON file (GDPR rights of access and portability), gathered from every AccountDataProviderInterface. Fully authenticated: a remembered session does not hand personal data out
class AccountExportController extends AbstractController
{
    public function __construct(
        private readonly AccountDataCollector $collector,
    ) {
    }

    // Downloaded rather than shown, named after the day it was taken
    #[Route('/account/export', name: 'config_account_export', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function export(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw $this->createNotFoundException();
        }

        $response = new JsonResponse($this->collector->collect($user));
        $response->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'account-data-' . date('Y-m-d') . '.json'));

        return $response;
    }
}
