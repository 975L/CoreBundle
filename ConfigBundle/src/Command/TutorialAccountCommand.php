<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Security\RolePreview;
use c975L\ConfigBundle\Service\TutorialAccount;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Opens and closes the throwaway account a browser-driving tool signs in with (see TutorialAccount), printing its credentials as one JSON line for that tool to read. Development only: an account whose password travels through a console output has no business on a production site
#[AsCommand(
    name: 'c975l:config:tutorial-account',
    description: 'Opens (or closes) a local throwaway account to drive the back office with - tutorials, end-to-end runs'
)]
class TutorialAccountCommand extends Command
{
    public function __construct(
        private readonly TutorialAccount $tutorialAccount,
        private readonly RolePreview $rolePreview,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email of the account', TutorialAccount::DEFAULT_EMAIL)
            ->addOption('as', null, InputOption::VALUE_REQUIRED, 'Level the account is opened at: contributor, editor, admin or super_admin', 'contributor')
            ->addOption('close', null, InputOption::VALUE_NONE, 'Remove the account, once the shoot is over')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ('dev' !== $this->environment) {
            $output->writeln(sprintf('<error>The tutorial account is a local one, refused in the "%s" environment.</error>', $this->environment));

            return Command::FAILURE;
        }

        $email = (string) $input->getOption('email');

        if ($input->getOption('close')) {
            $output->writeln($this->tutorialAccount->close($email) ? 'Tutorial account removed.' : 'No tutorial account to remove.');

            return Command::SUCCESS;
        }

        $level = (string) $input->getOption('as');
        $role = $this->rolePreview->ladder()[$level] ?? null;
        if (null === $role) {
            $output->writeln(sprintf('<error>No role for "%s" - one of %s, its site-role-* config filled.</error>', $level, implode(', ', array_keys($this->rolePreview->ladder()))));

            return Command::FAILURE;
        }

        $output->writeln(json_encode([
            'email' => $email,
            'password' => $this->tutorialAccount->open($email, [$role]),
        ], \JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
