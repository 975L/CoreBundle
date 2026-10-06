<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Mails site-backup-mailto the errors a health check run has just turned up, each one once while it lasts so a known failure is not mailed at every run - the dashboard alert reaching only an admin who opens the dashboard
class HealthCheckAlertMailer
{
    // The (kind, url) already mailed and still in error, kept in cache.app rather than read from the results so a send that failed is retried at the next run instead of being taken for one already done
    public const MAILED_CACHE_KEY = 'c975l_health_check_alert_mailed';

    public function __construct(
        private readonly HealthCheckResultRepository $healthCheckResultRepository,
        private readonly ConfigServiceInterface $configService,
        private readonly EmailService $emailService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    // Mails the errors among the given kinds not mailed yet, and says how many - null when there were some but no mail left
    /** @param list<string> $kinds the kinds the run actually checked */
    public function notify(array $kinds): ?int
    {
        $item = $this->cache->getItem(self::MAILED_CACHE_KEY);
        $mailed = $item->isHit() ? (array) $item->get() : [];

        // Back to ok on a kind that ran: forgotten, so its next failure is mailed again
        $errors = [];
        foreach ($this->healthCheckResultRepository->findLatestPerUrlAndKind() as $result) {
            if (!\in_array($result->getKind(), $kinds, true)) {
                continue;
            }

            $key = $this->key($result);
            if (HealthCheckResult::STATUS_ERROR !== $result->getStatus()) {
                unset($mailed[$key]);
            } elseif (!isset($mailed[$key])) {
                $errors[$key] = $result;
            }
        }

        $this->cache->save($item->set($mailed));

        if ([] === $errors) {
            return 0;
        }

        // No recipient: the dashboard alert is all this install asked for
        $mailto = (string) $this->configService->get('site-backup-mailto');
        if ('' === $mailto) {
            return 0;
        }

        // Same sender rule as BackupDigestCommand: the recipient doubles as the sender when email-from is empty
        $from = (string) $this->configService->get('email-from');
        $sender = '' !== $from ? $from : $mailto;
        $site = (string) $this->configService->get('site-name');

        $sent = $this->emailService->send(new EmailSendRequest(
            subject: sprintf('[%s] Health check: %d new error(s)', '' !== $site ? $site : (string) $this->configService->get('site-url'), \count($errors)),
            context: [],
            from: $sender,
            to: $mailto,
            replyTo: $sender,
            text: $this->body($errors),
        ));

        if (!$sent) {
            return null;
        }

        // Only once the mail is out, so a failed send leaves these errors to the next run
        $this->cache->save($item->set($mailed + array_fill_keys(array_keys($errors), true)));

        return \count($errors);
    }

    // One line per error, then the page where they are all listed
    /** @param array<HealthCheckResult> $errors */
    private function body(array $errors): string
    {
        $lines = array_map(
            static fn (HealthCheckResult $result): string => sprintf('- [%s] %s: %s', $result->getKind(), $result->getLabel() ?? $result->getUrl(), $result->getSummary()),
            $errors
        );

        // Absolute: HealthCheckRunCommand points the RequestContext at site-url before any of this runs
        return implode("\n", $lines) . "\n\n" . $this->urlGenerator->generate('management_health_check_index', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function key(HealthCheckResult $result): string
    {
        return $result->getKind() . '|' . $result->getUrl();
    }
}
