<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

/**
 * One file travelling with an email (see EmailSendRequest::$attachments).
 *
 * The bytes and not a path: what is attached is commonly a document drawn for that very message and written
 * nowhere (see PdfGeneratorInterface), and a caller holding a file passes file_get_contents() of it. Emails are
 * small by nature and a mail server refuses a big one long before memory becomes the question.
 *
 * Attaching a document rather than linking to it is not a matter of taste where the law asks for a "durable
 * medium": a link points at a page that can be rewritten, where a file the customer keeps cannot.
 */
final readonly class EmailAttachment
{
    public function __construct(
        // What the recipient sees the file called, extension included
        public string $filename,
        // The file itself
        public string $content,
        public string $contentType = 'application/pdf',
    ) {
    }
}
