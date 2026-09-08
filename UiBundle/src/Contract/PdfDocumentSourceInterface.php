<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Lets any bundle hand its own PDF documents to PdfThumbnailHealthCheckProvider, which otherwise only ever sees the medias of this bundle's own library. A satellite bundle holding its documents in a table of its own (BookBundle's book_media, say) is invisible to that check without this: its PDFs would silently render the fallback picture, which is the very thing the check exists to catch. Implement it and the service is auto-discovered by PdfDocumentSourcePass - see Readme.
interface PdfDocumentSourceInterface
{
    // Every PDF this bundle serves, whatever the entity holding it: "filename" is the path it is served under, relative to public/ and as the row stores it, the very string the thumbnail is derived from (see VichPdfThumbnailListener::toWebpPath()), "label" names it on the dashboard and "editUrl" leads to the screen it is edited on, null for a document no back-office screen opens
    /** @return list<array{filename: string, label: string, editUrl: ?string}> */
    public function getPdfDocuments(): array;
}
