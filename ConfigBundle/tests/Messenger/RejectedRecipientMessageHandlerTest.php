<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Messenger;

use c975L\ConfigBundle\Messenger\RejectedRecipientMessageHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\Messenger\MessageHandler;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

class RejectedRecipientMessageHandlerTest extends TestCase
{
    // Builds the email message sent to a generic recipient
    private function message(): SendEmailMessage
    {
        return new SendEmailMessage(new Email()->from('sender@example.com')->to('item-1@example.com')->text('Body'));
    }

    // Builds the decorator over a real handler whose transport throws the given SMTP answer, or returns the given sent message
    private function handler(?string $response, LoggerInterface $logger, ?SentMessage $sentMessage = null): RejectedRecipientMessageHandler
    {
        $transport = $this->createStub(TransportInterface::class);
        if (null !== $response) {
            $transport->method('send')->willThrowException(new UnexpectedResponseException(\sprintf('Expected response code "250" but got code "%d", with message "%s".', (int) $response, $response), (int) $response));
        } else {
            $transport->method('send')->willReturn($sentMessage);
        }

        return new RejectedRecipientMessageHandler(new MessageHandler($transport), $logger);
    }

    // An address refused for its reputation is dropped with a warning instead of being retried
    public function testPolicyRejectionOfTheRecipientIsDroppedWithAWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->anything(), $this->callback(fn (array $context) => 'item-1@example.com' === $context['recipient']));

        $this->assertNull($this->handler('554 5.7.1 item-1@example.com is rbl blacklisted', $logger)($this->message()));
    }

    // A 5.7.1 that names no recipient (sender refused, relay denied) still fails, being the site's own problem
    public function testPolicyRejectionNamingNoRecipientStillFails(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        $this->handler('554 5.7.1 sender@example.com relay access denied', $this->createStub(LoggerInterface::class))($this->message());
    }

    // A relay denied names the recipient but is the site's own SMTP configuration, so it still fails
    public function testRelayDeniedNamingTheRecipientStillFails(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        $this->handler('554 5.7.1 <item-1@example.com>: Relay access denied', $this->createStub(LoggerInterface::class))($this->message());
    }

    // A temporary failure naming the recipient is left to the retry strategy
    public function testTemporaryFailureStillFails(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        $this->handler('451 4.7.1 item-1@example.com greylisted', $this->createStub(LoggerInterface::class))($this->message());
    }

    // A permanent failure other than a policy one (unknown mailbox) still reaches the failed messages
    public function testOtherPermanentFailureStillFails(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        $this->handler('550 5.1.1 item-1@example.com user unknown', $this->createStub(LoggerInterface::class))($this->message());
    }

    // A sent email goes through untouched
    public function testSentEmailIsReturned(): void
    {
        $message = $this->message();
        $sentMessage = new SentMessage($message->getMessage(), Envelope::create($message->getMessage()));

        $this->assertSame($sentMessage, $this->handler(null, $this->createStub(LoggerInterface::class), $sentMessage)($message));
    }
}
