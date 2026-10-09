<?php

namespace App\EventListener;

use App\Entity\User;
use App\Repository\ResetPasswordRequestRepository;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

// An anonymized account keeps its row, so its pending password resets are deleted here rather than by the "CASCADE" of ResetPasswordRequest
#[AsEventListener]
class ResetPasswordRequestAnonymizedListener
{
    public function __construct(
        private readonly ResetPasswordRequestRepository $resetPasswordRequestRepository,
    ) {
    }

    // Deletes the requests of the account just anonymized
    public function __invoke(UserAnonymizedEvent $event): void
    {
        if ($event->user instanceof User) {
            $this->resetPasswordRequestRepository->removeForUser($event->user);
        }
    }
}
