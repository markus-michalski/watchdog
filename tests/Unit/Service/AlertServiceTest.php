<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\AlertState;
use App\Entity\CheckResult;
use App\Entity\Site;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use App\Message\MailNotificationMessage;
use App\Repository\AlertStateRepository;
use App\Service\AlertService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class AlertServiceTest extends TestCase
{
    #[Test]
    public function testTransitionFromOkToFailDispatchesFailureNotification(): void
    {
        $check = $this->createSiteCheck();
        $state = $this->createAlertState(CheckStatus::Ok);
        $result = $this->createCheckResult($check, CheckStatus::Fail);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (MailNotificationMessage $message): bool => 'failure' === $message->action))
            ->willReturn(new Envelope(new \stdClass()));

        $this->createAlertService($check, $state, $bus)->evaluate($check, $result);
    }

    #[Test]
    public function testTransitionFromFailToOkDispatchesRecoveryNotification(): void
    {
        $check = $this->createSiteCheck();
        $state = $this->createAlertState(CheckStatus::Fail);
        $result = $this->createCheckResult($check, CheckStatus::Ok);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (MailNotificationMessage $message): bool => 'recovery' === $message->action))
            ->willReturn(new Envelope(new \stdClass()));

        $this->createAlertService($check, $state, $bus)->evaluate($check, $result);
    }

    #[Test]
    public function testTransitionFromOkToOkDispatchesNoNotification(): void
    {
        $check = $this->createSiteCheck();
        $state = $this->createAlertState(CheckStatus::Ok);
        $result = $this->createCheckResult($check, CheckStatus::Ok);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->createAlertService($check, $state, $bus)->evaluate($check, $result);
    }

    #[Test]
    public function testTransitionFromFailToFailDispatchesNoNotification(): void
    {
        $check = $this->createSiteCheck();
        $state = $this->createAlertState(CheckStatus::Fail);
        $result = $this->createCheckResult($check, CheckStatus::Fail);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->createAlertService($check, $state, $bus)->evaluate($check, $result);
    }

    private function createAlertService(SiteCheck $check, AlertState $state, MessageBusInterface $bus): AlertService
    {
        $repository = $this->createMock(AlertStateRepository::class);
        $repository->method('findOrCreateForCheck')->with($check)->willReturn($state);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        return new AlertService($repository, $em, $bus);
    }

    private function createSiteCheck(): SiteCheck
    {
        $check = new SiteCheck();
        $check->setSite(new Site());
        $check->setType('http');
        $this->setId($check, 1);

        return $check;
    }

    private function createAlertState(CheckStatus $status): AlertState
    {
        $state = new AlertState();
        $state->setCurrentStatus($status);

        return $state;
    }

    private function createCheckResult(SiteCheck $check, CheckStatus $status): CheckResult
    {
        $result = new CheckResult();
        $result->setCheck($check);
        $result->setStatus($status);
        $this->setId($result, 2);

        return $result;
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
