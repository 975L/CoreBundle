<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use c975L\UiBundle\Model\EmailAttachment;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * What a bundle declares so a document of its own can be ticked in the email builder and travel with an email.
 *
 * Which files an email carries is an admin's answer, stored on the EmailTemplate beside the blocks that make up
 * its body - the same rule as everything else about a transactional email. A bundle only says what it is able to
 * draw, and draws it when asked; it never decides that an order confirmation carries the terms of sale, because
 * that is a shopkeeper's decision about their own shop and it belongs in the database.
 *
 * Auto-discovered: implement it and the service is registered, no tag needed (see EmailAttachmentProviderPass).
 */
interface EmailAttachmentProviderInterface
{
    /**
     * The documents this provider can draw, as the builder lists them.
     *
     * A kind is stored as it is in the template's row, so it is renamed the way a column is: never in place.
     *
     * @return array<string, TranslatableInterface> kind => how the builder names it, each carrying its own
     *                                              translation domain: a document declared by a shop is named in
     *                                              that bundle's own catalogue, not in this one's
     */
    public function getAttachmentKinds(): array;

    /**
     * The document itself, or null when there is nothing to attach.
     *
     * Null and not an exception for the ordinary cases: a kind another provider owns, an order holding no gift
     * card, an invoice not drawn yet. An email that leaves without one of its files is better than an order that
     * is never confirmed at all, so a provider unable to draw its document says so rather than throwing.
     *
     * @param array<string, mixed> $context what the caller was sending about - e.g. "basket", plus the "locale" the
     *                                      recipient is being written to, which is the language the document is drawn in
     */
    public function createAttachment(string $kind, array $context): ?EmailAttachment;
}
