<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Messenger;

use c975L\ConfigBundle\Service\MessengerFailedMessageService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\Messenger\MessageHandler;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\SentMessage;

// Decorates Symfony's mailer handler so a recipient refused for good by the SMTP server for its reputation (5.7.1 naming an address on a RBL) is dropped with a warning, instead of being retried then logged as CRITICAL
class RejectedRecipientMessageHandler
{
    // SMTP enhanced status code of a delivery refused on a policy ground
    private const string POLICY_REJECTION = '5.7.1';

    public function __construct(
        private readonly MessageHandler $handler,
        private readonly LoggerInterface $logger,
    ) {
    }

    // Sends the email through the decorated handler, swallowing a reputation rejection of one of its recipients
    public function __invoke(SendEmailMessage $message): ?SentMessage
    {
        try {
            return ($this->handler)($message);
        } catch (UnexpectedResponseException $exception) {
            $recipient = $this->rejectedRecipient($message, $exception);
            if (null === $recipient) {
                throw $exception;
            }

            $this->logger->warning('Email dropped, its recipient being refused by the SMTP server: {error}', [
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    // Returns the recipient a permanent 5.7.1 rejection for its reputation names, null for any other failure (relay denied, sender, authentication, server down...)
    private function rejectedRecipient(SendEmailMessage $message, UnexpectedResponseException $exception): ?string
    {
        $error = $exception->getMessage();
        if (5 !== intdiv($exception->getCode(), 100) || !str_contains($error, self::POLICY_REJECTION) || !MessengerFailedMessageService::isMinor($error)) {
            return null;
        }

        $envelope = $message->getEnvelope() ?? Envelope::create($message->getMessage());
        foreach ($envelope->getRecipients() as $address) {
            if (false !== stripos($error, $address->getAddress())) {
                return $address->getAddress();
            }
        }

        return null;
    }
}
