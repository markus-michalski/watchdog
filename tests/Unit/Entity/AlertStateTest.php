<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AlertState;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AlertStateTest extends TestCase
{
    #[Test]
    public function transitionFromOkToFailSetsStatusAndFailCount(): void
    {
        $state = new AlertState();
        $state->setCurrentStatus(CheckStatus::Ok);

        $state->transitionTo(CheckStatus::Fail);

        self::assertSame(CheckStatus::Fail, $state->getCurrentStatus());
        self::assertSame(1, $state->getFailCount());
    }

    #[Test]
    public function repeatedFailIncrementsFailCount(): void
    {
        $state = new AlertState();
        $state->setCurrentStatus(CheckStatus::Ok);

        $state->transitionTo(CheckStatus::Fail);
        $state->transitionTo(CheckStatus::Fail);

        self::assertSame(CheckStatus::Fail, $state->getCurrentStatus());
        self::assertSame(2, $state->getFailCount());
    }

    #[Test]
    public function transitionFromFailToOkResetsFailCount(): void
    {
        $state = new AlertState();
        $state->setCurrentStatus(CheckStatus::Ok);
        $state->transitionTo(CheckStatus::Fail);

        $state->transitionTo(CheckStatus::Ok);

        self::assertSame(CheckStatus::Ok, $state->getCurrentStatus());
        self::assertSame(0, $state->getFailCount());
    }

    #[Test]
    public function lastStatusChangeIsUpdatedOnRealStatusChange(): void
    {
        $state = new AlertState();
        $state->setCurrentStatus(CheckStatus::Ok);
        $past = new \DateTimeImmutable('-1 hour');
        $state->setLastStatusChange($past);

        $state->transitionTo(CheckStatus::Fail);

        self::assertGreaterThan($past, $state->getLastStatusChange());
    }

    #[Test]
    public function lastStatusChangeIsNotUpdatedWhenStatusStaysTheSame(): void
    {
        $state = new AlertState();
        $state->setCurrentStatus(CheckStatus::Fail);
        $fixed = new \DateTimeImmutable('-1 hour');
        $state->setLastStatusChange($fixed);

        $state->transitionTo(CheckStatus::Fail);

        self::assertEquals($fixed, $state->getLastStatusChange());
    }
}
