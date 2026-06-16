<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent;

use App\Agent\LocalCheckRunner;
use App\Check\CheckInterface;
use App\Check\CheckRegistry;
use App\Entity\CheckResult;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LocalCheckRunnerTest extends TestCase
{
    private CheckRegistry&MockObject $registry;
    private LocalCheckRunner $runner;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(CheckRegistry::class);
        $this->runner = new LocalCheckRunner($this->registry);
    }

    #[Test]
    public function returnsUnknownForUnregisteredType(): void
    {
        $this->registry->method('has')->with('unknown-type')->willReturn(false);

        $result = $this->runner->run(42, 'unknown-type', []);

        $this->assertSame(42, $result['site_check_id']);
        $this->assertSame('unknown', $result['status']);
        $this->assertStringContainsString('unknown-type', $result['message']);
        $this->assertNull($result['response_time_ms']);
        $this->assertArrayHasKey('checked_at', $result);
    }

    #[Test]
    public function runsCheckAndReturnsStatusAndMessage(): void
    {
        $checkResult = new CheckResult();
        $checkResult->setStatus(CheckStatus::Ok);
        $checkResult->setMessage('All good');
        $checkResult->setResponseTimeMs(15);

        $check = $this->createMock(CheckInterface::class);
        $check->method('run')->willReturn($checkResult);

        $this->registry->method('has')->with('process')->willReturn(true);
        $this->registry->method('get')->with('process')->willReturn($check);

        $result = $this->runner->run(1, 'process', ['process_name' => 'nginx']);

        $this->assertSame(1, $result['site_check_id']);
        $this->assertSame('ok', $result['status']);
        $this->assertSame('All good', $result['message']);
        $this->assertSame(15, $result['response_time_ms']);
    }

    #[Test]
    public function fallsBackToElapsedTimeWhenCheckSetsNoResponseTime(): void
    {
        $checkResult = new CheckResult();
        $checkResult->setStatus(CheckStatus::Fail);
        $checkResult->setMessage('Down');

        $check = $this->createMock(CheckInterface::class);
        $check->method('run')->willReturn($checkResult);

        $this->registry->method('has')->willReturn(true);
        $this->registry->method('get')->willReturn($check);

        $result = $this->runner->run(1, 'process', []);

        $this->assertNotNull($result['response_time_ms']);
        $this->assertGreaterThanOrEqual(0, $result['response_time_ms']);
    }

    #[Test]
    public function passesConfigToCheckViaProxy(): void
    {
        $capturedProxy = null;

        $checkResult = new CheckResult();
        $checkResult->setStatus(CheckStatus::Ok);

        $check = $this->createMock(CheckInterface::class);
        $check->method('run')->willReturnCallback(function (SiteCheck $proxy) use (&$capturedProxy, $checkResult) {
            $capturedProxy = $proxy;
            return $checkResult;
        });

        $this->registry->method('has')->willReturn(true);
        $this->registry->method('get')->willReturn($check);

        $config = ['process_name' => 'redis-server'];
        $this->runner->run(1, 'process', $config);

        $this->assertNotNull($capturedProxy);
        $this->assertSame($config, $capturedProxy->getConfig());
        $this->assertSame('process', $capturedProxy->getType());
    }

    #[Test]
    public function checkedAtIsIso8601(): void
    {
        $checkResult = new CheckResult();
        $checkResult->setStatus(CheckStatus::Ok);

        $check = $this->createMock(CheckInterface::class);
        $check->method('run')->willReturn($checkResult);

        $this->registry->method('has')->willReturn(true);
        $this->registry->method('get')->willReturn($check);

        $result = $this->runner->run(1, 'process', []);

        $dt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $result['checked_at']);
        $this->assertNotFalse($dt, 'checked_at must be ATOM format');
    }
}
