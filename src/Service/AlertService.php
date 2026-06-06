<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use App\Message\MailNotificationMessage;
use App\Repository\AlertStateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class AlertService
{
    public function __construct(
        private readonly AlertStateRepository $alertStateRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {}

    public function evaluate(SiteCheck $check, CheckResult $result): void
    {
        $state = $this->alertStateRepository->findOrCreateForCheck($check);
        $previousStatus = $state->getCurrentStatus();
        $newStatus = $result->getStatus();

        $state->transitionTo($newStatus);
        $this->em->flush();

        // Notify on transition ok→fail (first failure) or fail→ok (recovery)
        if ($previousStatus !== CheckStatus::Fail && $newStatus === CheckStatus::Fail) {
            $this->bus->dispatch(new MailNotificationMessage(
                siteCheckId: $check->getId(),
                checkResultId: $result->getId(),
                action: 'failure',
            ));

            return;
        }

        if ($previousStatus === CheckStatus::Fail && $newStatus === CheckStatus::Ok) {
            $this->bus->dispatch(new MailNotificationMessage(
                siteCheckId: $check->getId(),
                checkResultId: $result->getId(),
                action: 'recovery',
            ));
        }
    }
}
