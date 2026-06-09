<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Check\CheckRegistry;
use App\Entity\Site;
use App\Entity\SiteCheck;
use App\Message\RunSiteChecksMessage;
use App\MessageHandler\RunSiteChecksHandler;
use App\Repository\AlertStateRepository;
use App\Repository\SiteCheckRepository;
use App\Service\AlertService;
use App\Service\CheckRunner;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class RunSiteChecksHandlerTest extends TestCase
{
    #[Test]
    public function doesNothingWhenCheckNotFound(): void
    {
        $repository = $this->createMock(SiteCheckRepository::class);
        $repository->method('find')->with(42)->willReturn(null);

        $registry = $this->createRegistrySpy(shouldRun: false);

        $this->createHandler($repository, $registry)(new RunSiteChecksMessage(42));
    }

    #[Test]
    public function doesNothingWhenCheckIsInactive(): void
    {
        $check = $this->createCheck(checkActive: false, siteActive: true);

        $repository = $this->createMock(SiteCheckRepository::class);
        $repository->method('find')->with(1)->willReturn($check);

        $registry = $this->createRegistrySpy(shouldRun: false);

        $this->createHandler($repository, $registry)(new RunSiteChecksMessage(1));
    }

    #[Test]
    public function doesNothingWhenSiteIsInactive(): void
    {
        $check = $this->createCheck(checkActive: true, siteActive: false);

        $repository = $this->createMock(SiteCheckRepository::class);
        $repository->method('find')->with(1)->willReturn($check);

        $registry = $this->createRegistrySpy(shouldRun: false);

        $this->createHandler($repository, $registry)(new RunSiteChecksMessage(1));
    }

    #[Test]
    public function runsCheckWhenCheckAndSiteAreActive(): void
    {
        $check = $this->createCheck(checkActive: true, siteActive: true);

        $repository = $this->createMock(SiteCheckRepository::class);
        $repository->method('find')->with(1)->willReturn($check);

        $registry = $this->createRegistrySpy(shouldRun: true);

        $this->createHandler($repository, $registry)(new RunSiteChecksMessage(1));
    }

    private function createHandler(SiteCheckRepository $repository, CheckRegistry $registry): RunSiteChecksHandler
    {
        $alertService = new AlertService(
            $this->createStub(AlertStateRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(MessageBusInterface::class),
        );

        $checkRunner = new CheckRunner(
            $registry,
            $this->createStub(EntityManagerInterface::class),
            $alertService,
            $this->createStub(LoggerInterface::class),
        );

        return new RunSiteChecksHandler($repository, $checkRunner);
    }

    private function createRegistrySpy(bool $shouldRun): CheckRegistry&MockObject
    {
        $registry = $this->createMock(CheckRegistry::class);

        if ($shouldRun) {
            $registry->expects(self::once())->method('has')->willReturn(false);
        } else {
            $registry->expects(self::never())->method('has');
        }

        return $registry;
    }

    private function createCheck(bool $checkActive, bool $siteActive): SiteCheck
    {
        $site = new Site();
        $site->setName('Example');
        $site->setIsActive($siteActive);

        $check = new SiteCheck();
        $check->setSite($site);
        $check->setType('http');
        $check->setIsActive($checkActive);

        return $check;
    }
}
