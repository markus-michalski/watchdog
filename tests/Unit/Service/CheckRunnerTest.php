<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Check\CheckInterface;
use App\Check\CheckRegistry;
use App\Entity\AlertState;
use App\Entity\CheckResult;
use App\Entity\Site;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use App\Repository\AlertStateRepository;
use App\Service\AlertService;
use App\Service\CheckRunner;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class CheckRunnerTest extends TestCase
{
    #[Test]
    public function runWithUnknownTypeLogsWarningAndDoesNothing(): void
    {
        $check = $this->createSiteCheck('mystery');

        $registry = $this->createMock(CheckRegistry::class);
        $registry->method('has')->with('mystery')->willReturn(false);
        $registry->expects(self::never())->method('get');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $alertStateRepository = $this->createMock(AlertStateRepository::class);
        $alertStateRepository->expects(self::never())->method('findOrCreateForCheck');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $runner = new CheckRunner(
            $registry,
            $em,
            $this->createAlertService($alertStateRepository),
            $logger,
        );

        $runner->run($check);
    }

    #[Test]
    public function runWithKnownTypePersistsResultAndEvaluatesAlert(): void
    {
        $check = $this->createSiteCheck('http');
        $result = new CheckResult();
        $result->setCheck($check);
        $result->setStatus(CheckStatus::Ok);

        $implementation = $this->createMock(CheckInterface::class);
        $implementation->expects(self::once())
            ->method('run')
            ->with($check)
            ->willReturn($result);

        $registry = $this->createMock(CheckRegistry::class);
        $registry->method('has')->with('http')->willReturn(true);
        $registry->method('get')->with('http')->willReturn($implementation);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($result);
        $em->expects(self::once())->method('flush');

        $state = new AlertState();
        $state->setCurrentStatus(CheckStatus::Ok);

        $alertStateRepository = $this->createMock(AlertStateRepository::class);
        $alertStateRepository->expects(self::once())
            ->method('findOrCreateForCheck')
            ->with($check)
            ->willReturn($state);

        $runner = new CheckRunner(
            $registry,
            $em,
            $this->createAlertService($alertStateRepository),
            $this->createStub(LoggerInterface::class),
        );

        $runner->run($check);
    }

    private function createAlertService(AlertStateRepository $alertStateRepository): AlertService
    {
        return new AlertService(
            $alertStateRepository,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(MessageBusInterface::class),
        );
    }

    private function createSiteCheck(string $type): SiteCheck
    {
        $site = new Site();
        $site->setName('Example');

        $check = new SiteCheck();
        $check->setSite($site);
        $check->setType($type);

        return $check;
    }
}
