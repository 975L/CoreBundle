<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Model\EmailSendRequest;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// Tells the site that someone has just written about it - nothing of a review shows until it is read, so without this one waits in a screen nobody has a reason to open. Sent to the site's own "email-to" address, the one every c975L bundle already writes to, and modelled on ConfigBundle's own UserCreationNotifier
class ReviewNotifier
{
    // What of the review travels in the email: enough to know whether it needs answering today, not a copy of a text that is one click away
    private const int EXCERPT_LENGTH = 300;

    public function __construct(
        private readonly EmailService $emailService,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        // The site's own language, not the visitor's: this goes to the owner, who has no reason to read it in whatever locale the person writing was browsing in
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    // Returns false when EmailService could not send it (no "email-from"/"email-to" seeded, mailer down) - a review is never worth failing over a notification its author knows nothing about, so the caller is free to ignore the result
    public function notify(Review $review): bool
    {
        return $this->emailService->send(new EmailSendRequest(
            subject: $this->subject(),
            context: [],
            // Plain text, as every operational email these bundles send: it goes to an administrator, not to a visitor, and gains nothing from a branded layout
            text: $this->translator->trans(
                'text.review_submitted_notification',
                [
                    '%author%' => (string) $review->getAuthorName(),
                    '%rating%' => null === $review->getRating() ? '-' : $review->getRating() . '/' . Review::SCALE,
                    '%comment%' => $this->excerpt($review),
                ],
                'ui',
                $this->defaultLocale,
            ),
        ));
    }

    // Prefixed with the site name when one is set, several sites otherwise sending an inbox the very same subject
    private function subject(): string
    {
        $label = $this->translator->trans('label.review_submitted_notification', [], 'ui', $this->defaultLocale);
        $siteName = (string) $this->configService->get('site-name');

        return '' === $siteName ? $label : $siteName . ' - ' . $label;
    }

    private function excerpt(Review $review): string
    {
        $comment = (string) $review->getComment();

        return mb_strlen($comment) > self::EXCERPT_LENGTH
            ? mb_substr($comment, 0, self::EXCERPT_LENGTH) . '...'
            : $comment;
    }
}
