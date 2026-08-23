<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\EmailAttachmentProviderInterface;
use c975L\UiBundle\Model\EmailAttachment;
use Symfony\Contracts\Translation\TranslatableInterface;

// Every document the installed bundles are able to attach to an email - read by the builder, which lists them as checkboxes, and by the sending, which draws the ones that were ticked
class EmailAttachmentRegistry
{
    /** @var EmailAttachmentProviderInterface[] */
    private array $providers = [];

    public function addProvider(EmailAttachmentProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Every kind on offer, kind => how it is named.
     *
     * @return array<string, TranslatableInterface>
     */
    public function getKinds(): array
    {
        $kinds = [];
        foreach ($this->providers as $provider) {
            $kinds += $provider->getAttachmentKinds();
        }

        ksort($kinds);

        return $kinds;
    }

    /**
     * The files to send, drawn in the order the kinds are given.
     *
     * A kind no installed bundle owns is skipped rather than reported: a site that removes a bundle keeps rows
     * naming what it used to draw, and an order confirmation must still go out.
     *
     * @param list<string>         $kinds
     * @param array<string, mixed> $context
     *
     * @return list<EmailAttachment>
     */
    public function resolve(array $kinds, array $context = []): array
    {
        $attachments = [];
        foreach ($kinds as $kind) {
            foreach ($this->providers as $provider) {
                if (!\array_key_exists($kind, $provider->getAttachmentKinds())) {
                    continue;
                }

                $attachment = $provider->createAttachment($kind, $context);
                if (null !== $attachment) {
                    $attachments[] = $attachment;
                }

                break;
            }
        }

        return $attachments;
    }
}
