<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests;

use c975L\ConfigBundle\Test\CodeRulesTestCase;

// The audit's rules over this bundle's own code (see CodeRulesTestCase)
class CodeRulesTest extends CodeRulesTestCase
{
    protected function sourceDirectory(): string
    {
        return __DIR__ . '/../src';
    }

    protected function sourceNamespace(): string
    {
        return 'c975L\ConfigBundle';
    }

    // Bulk queries on tables no cached fragment reads: logs, tokens, queues and accounts, all read live
    protected function allowed(): array
    {
        return [
            self::BULK_QUERY => [
                'c975L\ConfigBundle\Command\SessionsCleanupCommand::execute' => 'The sessions table is read by the session handler only, never cached',
                'c975L\ConfigBundle\Repository\ActivityLogRepository::purgeOlderThan' => 'The activity log is read live by its back-office list, never cached',
                'c975L\ConfigBundle\Repository\AppTokenRepository::deleteOfUser' => 'App tokens are read live at each authentication, never cached',
                'c975L\ConfigBundle\Repository\AppTokenRepository::deleteExpiredCodes' => 'App tokens are read live at each authentication, never cached',
                'c975L\ConfigBundle\Repository\HealthCheckResultRepository::deleteOlderThan' => 'Health check rows are read live by the dashboard and the status report, never cached',
                'c975L\ConfigBundle\Repository\HealthCheckResultRepository::deleteByKindNotInUrls' => 'Health check rows are read live by the dashboard and the status report, never cached',
                'c975L\ConfigBundle\Repository\NotFoundRepository::record' => 'The 404 log is read live by its back-office list, never cached (RedirectSubscriber caches the redirects, not this table)',
                'c975L\ConfigBundle\Repository\NotFoundRepository::purgeOlderThan' => 'The 404 log is read live by its back-office list, never cached (RedirectSubscriber caches the redirects, not this table)',
                'c975L\ConfigBundle\Service\InactiveUserFinder::startMissingClocks' => 'A last login date is account data, which no cached fragment holds',
                'c975L\ConfigBundle\Service\MessengerFailedMessageService::purgeOlderThan' => 'The messenger queue is read live by the failed messages screen, never cached',
                'c975L\ConfigBundle\Service\MessengerFailedMessageService::deleteById' => 'The messenger queue is read live by the failed messages screen, never cached',
                'c975L\ConfigBundle\Service\MessengerFailedMessageService::deleteByIds' => 'The messenger queue is read live by the failed messages screen, never cached',
            ],
        ];
    }
}
