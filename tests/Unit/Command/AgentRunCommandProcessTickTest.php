<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Agent\LocalCheckRunnerInterface;
use App\Command\AgentRunCommand;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AllowMockObjectsWithoutExpectations]
class AgentRunCommandProcessTickTest extends TestCase
{
    private LocalCheckRunnerInterface&MockObject $runner;
    private AgentRunCommand $command;
    private SymfonyStyle $io;

    protected function setUp(): void
    {
        $this->runner = $this->createMock(LocalCheckRunnerInterface::class);
        $this->command = new AgentRunCommand(
            $this->createMock(HttpClientInterface::class),
            $this->runner,
            new NullLogger(),
        );
        $this->io = new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
    }

    #[Test]
    public function runNowCheckFiresOnFirstTickAndClearsFlag(): void
    {
        $checks = [
            [
                'id' => 5,
                'type' => 'file_age',
                'run_now' => true,
                'run_at_time' => '08:00',
                'check_interval_minutes' => 1440,
                'config' => [],
            ],
        ];
        $lastRunAt = [];
        $lastRunDate = [];
        $now = time();

        $this->runner->expects($this->once())->method('run')
            ->with(5, 'file_age', [])
            ->willReturn(['id' => 5, 'status' => 'ok']);

        $this->command->processTick($checks, $now, $lastRunAt, $lastRunDate, $this->io);

        $this->assertFalse($checks[0]['run_now'], 'run_now must be cleared in-memory after the first tick');
    }

    #[Test]
    public function runNowCheckDoesNotFireAgainOnSecondTickAfterFlagCleared(): void
    {
        $checks = [
            [
                'id' => 5,
                'type' => 'file_age',
                'run_now' => true,
                'run_at_time' => '08:00',
                'check_interval_minutes' => 1440,
                'config' => [],
            ],
        ];
        $lastRunAt = [];
        $lastRunDate = [];
        $now = time();

        $this->runner->expects($this->once())->method('run')
            ->willReturn(['id' => 5, 'status' => 'ok']);

        // Tick 1: run_now triggers the check
        $this->command->processTick($checks, $now, $lastRunAt, $lastRunDate, $this->io);

        // Tick 2 (30s later): run_now is now false in-memory — daily check already ran today
        $this->command->processTick($checks, $now + 30, $lastRunAt, $lastRunDate, $this->io);

        // $this->runner->expects($this->once()) above asserts the check ran exactly once
    }

    #[Test]
    public function runNowFlagIsClearedEvenWhenCheckThrows(): void
    {
        $checks = [
            [
                'id' => 99,
                'type' => 'http',
                'run_now' => true,
                'run_at_time' => null,
                'check_interval_minutes' => 60,
                'config' => [],
            ],
        ];
        $lastRunAt = [];
        $lastRunDate = [];

        $this->runner->method('run')->willThrowException(new \RuntimeException('connection refused'));

        $this->command->processTick($checks, time(), $lastRunAt, $lastRunDate, $this->io);

        $this->assertFalse($checks[0]['run_now'], 'run_now must be cleared even when check execution fails');
    }

    #[Test]
    public function intervalCheckIsNotBypassedWithoutRunNow(): void
    {
        $checks = [
            [
                'id' => 7,
                'type' => 'disk_space',
                'run_now' => false,
                'run_at_time' => null,
                'check_interval_minutes' => 1440,
                'config' => [],
            ],
        ];
        $now = time();
        $lastRunAt = [7 => $now - 30];  // ran 30s ago, interval is 1440min
        $lastRunDate = [];

        $this->runner->expects($this->never())->method('run');

        $this->command->processTick($checks, $now, $lastRunAt, $lastRunDate, $this->io);
    }
}
