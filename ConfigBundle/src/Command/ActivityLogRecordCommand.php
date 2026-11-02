<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Entity\ActivityLog;
use c975L\ConfigBundle\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Adds a row to the activity log for what no flush went through, such as SQL run directly on a server, the statement itself not being kept. Usage: php bin/console c975l:config:activity-log-record --action=update --type=site_page --label="Fixed the slug of page 12" --by=someone@example.com
/**
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:config:activity-log-record',
    description: 'Adds a row to the activity log for a change made outside the back office'
)]
class ActivityLogRecordCommand extends Command
{
    // The size of the entity_id column
    private const int MAX_ID_LENGTH = 64;

    public function __construct(
        private readonly ActivityLogRepository $activityLogRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    // Declares what a note holds
    protected function configure(): void
    {
        $this
            ->addOption('action', null, InputOption::VALUE_REQUIRED, 'What was done: ' . implode(', ', ActivityLog::ACTIONS))
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'What was touched, eg. the table')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'What the change was, in a sentence holding no secret')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'The id of the row touched, if there is one')
            ->addOption('by', null, InputOption::VALUE_REQUIRED, 'The address of the account answering for it')
        ;
    }

    // Writes the note, once it says what was done and who answers for it
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $action = (string) $input->getOption('action');
        $type = (string) $input->getOption('type');
        $label = (string) $input->getOption('label');
        $id = $input->getOption('id');
        $by = (string) $input->getOption('by');

        if (!\in_array($action, ActivityLog::ACTIONS, true) || '' === $type || '' === $label || '' === $by) {
            $io->error(sprintf('--type, --label, --by and an --action among "%s" are required.', implode('", "', ActivityLog::ACTIONS)));

            return Command::INVALID;
        }

        // Refused rather than cut, a shortened id naming another row: this also spares the column's overflow and the critical alert it raises in production
        if (null !== $id && mb_strlen($id) > self::MAX_ID_LENGTH) {
            $io->error(sprintf('--id holds one row, in %d characters at most: several rows go in --label.', self::MAX_ID_LENGTH));

            return Command::INVALID;
        }

        // A note signed by nobody would read like the work of a deleted account, so a mistyped address stops here
        $user = $this->entityManager->getRepository(UserInterface::class)->findOneBy(['email' => $by]);
        if (!$user instanceof UserInterface) {
            $io->error(sprintf('No account with the address "%s".', $by));

            return Command::INVALID;
        }

        $this->activityLogRepository->record([new ActivityLog(
            new \DateTimeImmutable(),
            $user,
            $action,
            mb_substr($type, 0, 100),
            $id,
            mb_substr($label, 0, 255),
            [],
            ActivityLog::PATH_CONSOLE,
        )]);

        $io->success('Added to the activity log.');

        return Command::SUCCESS;
    }
}
