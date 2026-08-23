<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller;

use c975L\UiBundle\Service\LegalDocument;
use c975L\UiBundle\Service\LegalModelCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Any legal document the site publishes, as a file - the terms of sales a shop owes its customers on a durable medium, but the privacy policy and the legal notice the same way. Here rather than beside whichever bundle happens to display one: the models, the block carrying a site's own version and the renderer all live here already
class LegalDocumentController extends AbstractController
{
    public function __construct(
        private readonly LegalDocument $legalDocument,
        private readonly LegalModelCatalog $catalog,
    ) {
    }

    // The identifier is matched against the catalog before anything else: it is half a template path, and nothing from a url may ever reach one
    #[Route(
        '/legal/{model}.pdf',
        name: 'ui_legal_document_pdf',
        requirements: ['model' => '[a-z0-9-]+/[a-z0-9-]+'],
        methods: ['GET']
    )]
    public function pdf(string $model, Request $request): Response
    {
        if (!$this->catalog->has($model)) {
            throw $this->createNotFoundException();
        }

        $locale = $request->getLocale();
        $response = new Response($this->legalDocument->pdf($model, $locale));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition('inline', str_replace('/', '-', $model) . '.pdf'));

        // Kept by the browser only as long as the document has not moved: the fingerprint is in the tag, so a clause rewritten in the back-office invalidates it on its own
        $response->setEtag($this->legalDocument->fingerprint($model, $locale));
        $response->setPublic();

        return $response->isNotModified($request) ? $response : $response->setMaxAge(3600);
    }
}
