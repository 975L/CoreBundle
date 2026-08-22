<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Scheduler;

use c975L\ConfigBundle\Scheduler\ConfigMaintenanceTaskProvider;
use c975L\ConfigBundle\Scheduler\MaintenanceTask;
use PHPUnit\Framework\TestCase;

class ConfigMaintenanceTaskProviderTest extends TestCase
{
    private function getTasks(): array
    {
        return new ConfigMaintenanceTaskProvider()->getMaintenanceTasks();
    }

    public function testEveryDeclaredTaskIsAMaintenanceTask(): void
    {
        $tasks = $this->getTasks();

        $this->assertNotEmpty($tasks);
        $this->assertContainsOnlyInstancesOf(MaintenanceTask::class, $tasks);
    }

    public function testItDeclaresThisBundlesOwnCommands(): void
    {
        $commands = array_map(static fn (MaintenanceTask $task): string => $task->command, $this->getTasks());

        $this->assertContains('c975l:sitemaps:create', $commands);
        $this->assertContains('c975l:seo:files:create', $commands);
        $this->assertContains('c975l:config:backup', $commands);
        $this->assertContains('c975l:config:backup:offsite', $commands);
        $this->assertContains('c975l:config:backup:digest', $commands);
        $this->assertContains('c975l:config:messenger-cleanup', $commands);
        $this->assertContains('c975l:config:sessions-cleanup', $commands);
    }

    // The mirrored uploads are written once and weigh far more than everything else scheduled here, so they run nightly rather than riding the backup's own 6-hourly cadence
    public function testTheOffsiteMirrorRunsNightlyRatherThanEverySixHours(): void
    {
        $expressions = [];
        foreach ($this->getTasks() as $task) {
            $expressions[$task->command] = $task->expression;
        }

        $this->assertStringContainsString('*/6', $expressions['c975l:config:backup']);
        $this->assertStringNotContainsString('*/6', $expressions['c975l:config:backup:offsite']);
    }

    // A cadence, never a list of kinds: every provider declares its own with AsHealthCheck, so these two account for whatever bundles the site installs later
    public function testTheHealthCheckIsDeclaredByFrequencyRatherThanByKind(): void
    {
        $commands = array_map(static fn (MaintenanceTask $task): string => $task->command, $this->getTasks());

        $this->assertContains('c975l:health-check:run --frequency=weekly', $commands);
        $this->assertContains('c975l:health-check:run --frequency=monthly', $commands);

        foreach ($commands as $command) {
            $this->assertStringNotContainsString('--kind=', $command);
        }
    }

    // MaintenanceScheduleBuilder skips a command already scheduled, so a duplicate here would silently drop one of the two cadences
    public function testNoCommandIsDeclaredTwice(): void
    {
        $commands = array_map(static fn (MaintenanceTask $task): string => $task->command, $this->getTasks());

        $this->assertSame($commands, array_unique($commands));
    }

    // The whole point of declaring them here: every expression must have something for ScheduleSpreader to draw, or every install runs it on the same minute
    public function testEveryExpressionHasAPlaceholderToSpread(): void
    {
        foreach ($this->getTasks() as $task) {
            $this->assertStringContainsString('#', $task->expression, sprintf('"%s" is scheduled on a fixed expression.', $task->command));
        }
    }

    // Five fields, cron's own shape - a malformed one only fails at worker start-up, taking every other task with it
    public function testEveryExpressionHasFiveFields(): void
    {
        foreach ($this->getTasks() as $task) {
            $this->assertCount(5, explode(' ', $task->expression), sprintf('"%s" is not a five-field cron expression.', $task->command));
        }
    }
}
