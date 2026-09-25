<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Command;

use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\InactiveUserFinder;
use c975L\UiBundle\Model\EmailSendRequest;
use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Service\EmailTemplateRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Warns then anonymizes the accounts left unused (GDPR storage limitation), anonymized rather than deleted so the payments, invoices and credits referring to them stay for the accounting retention; "user-inactivity-days" at 0 disables it. Usage: php bin/console c975l:config:users-cleanup
/**
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:config:users-cleanup',
    description: 'Warns then anonymizes the accounts left unused'
)]
class UsersCleanupCommand extends Command
{
    // The admin-editable EmailTemplate of the notice, declared by UserFormSeeder
    public const string EMAIL_TEMPLATE = 'account_inactivity_notice';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly EmailService $emailService,
        private readonly EmailTemplateRenderer $emailTemplateRenderer,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly InactiveUserFinder $inactiveUserFinder,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $locale,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $this->configService->get('user-inactivity-days');
        if ($days <= 0) {
            $io->note('"user-inactivity-days" is 0, inactive accounts are kept.');

            return Command::SUCCESS;
        }

        // Shipped to every site, including those whose User doesn't track its inactivity yet
        if (!$this->inactiveUserFinder->isSupported()) {
            $io->note('The User entity does not implement InactivityAwareInterface, nothing to clean.');

            return Command::SUCCESS;
        }

        // A notice as long as the inactivity itself would warn every account, including those just created
        $noticeDays = max(0, (int) $this->configService->get('user-inactivity-notice-days'));
        if ($noticeDays >= $days) {
            $io->error('"user-inactivity-notice-days" must be lower than "user-inactivity-days".');

            return Command::FAILURE;
        }

        $this->inactiveUserFinder->startMissingClocks();

        // Anonymizes the accounts warned long enough ago, before warning new ones so an account is never both in the same run
        $anonymized = $this->inactiveUserFinder->findToAnonymize(new \DateTime('-' . $days . ' days'), new \DateTime('-' . $noticeDays . ' days'));
        foreach ($anonymized as $user) {
            $user->anonymize();
            $this->eventDispatcher->dispatch(new UserAnonymizedEvent($user));
        }

        // Warns the accounts about to reach the limit, the date being written down only once the email has left
        $notified = 0;
        foreach ($this->inactiveUserFinder->findToNotify(new \DateTime('-' . ($days - $noticeDays) . ' days')) as $user) {
            if ($this->notify($user, $noticeDays)) {
                $user->setInactivityNoticeSentAt(new \DateTime());
                ++$notified;
            }
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d account(s) warned, %d anonymized.', $notified, count($anonymized)));

        return Command::SUCCESS;
    }

    // Sends the notice, false when the template was deleted from the back-office or the email could not leave
    private function notify(InactivityAwareInterface $user, int $noticeDays): bool
    {
        $html = $this->emailTemplateRenderer->renderNamed(self::EMAIL_TEMPLATE, [
            'login_url' => rtrim((string) $this->configService->get('site-url'), '/') . $this->urlGenerator->generate('app_login'),
            'deadline' => $this->translator->trans('text.account_inactivity_deadline', ['%date%' => new \DateTime('+' . $noticeDays . ' days')->format('d/m/Y')], 'config', $this->locale),
        ], $this->locale);

        if (null === $html || null === $user->getEmail()) {
            return false;
        }

        return $this->emailService->send(new EmailSendRequest(
            subject: $this->translator->trans('label.account_inactivity_subject', [], 'config', $this->locale),
            context: [],
            html: $html,
            to: $user->getEmail(),
        ));
    }
}
