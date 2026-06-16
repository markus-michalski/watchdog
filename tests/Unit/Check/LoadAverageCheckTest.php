<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\LoadAverageCheck;
use App\Check\LoadAverageReaderInterface;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use App\Enum\RunnerMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoadAverageCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsLoadAverage(): void
    {
        self::assertSame('load_average', $this->makeCheck()->getType());
    }

    #[Test]
    public function testGetLabelReturnsHumanReadableLabel(): void
    {
        self::assertSame('Load Average', $this->makeCheck()->getLabel());
    }

    #[Test]
    public function testRunnerModeReturnsAgentOnly(): void
    {
        self::assertSame(RunnerMode::AgentOnly, $this->makeCheck()->runnerMode());
    }

    #[Test]
    public function testGetDefaultConfigReturnsExpectedDefaults(): void
    {
        $defaults = $this->makeCheck()->getDefaultConfig();

        self::assertSame(0.8, $defaults['warn_factor']);
        self::assertSame(1.5, $defaults['fail_factor']);
    }

    #[Test]
    public function testGetConfigSchemaContainsAllFields(): void
    {
        $names = array_column($this->makeCheck()->getConfigSchema(), 'name');

        self::assertContains('warn_factor', $names);
        self::assertContains('fail_factor', $names);
    }

    #[Test]
    public function testGetConfigSchemaMarksNoFieldAsRequired(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $required = array_filter($schema, fn (array $f) => $f['required']);

        self::assertEmpty($required);
    }

    #[Test]
    public function testRunReturnsUnknownWhenReaderReturnsNull(): void
    {
        // null = /proc unavailable (non-Linux)
        $result = $this->makeCheck(readerResult: null)->run($this->createSiteCheck());

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('unavailable', strtolower((string) $result->getMessage()));
    }

    #[Test]
    public function testRunReturnsFailWhenReaderReturnsErrorString(): void
    {
        $result = $this->makeCheck(readerResult: 'Cannot read /proc/loadavg')
            ->run($this->createSiteCheck());

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Cannot read', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsOkWhenLoadBelowWarnThreshold(): void
    {
        // load=0.5, cpus=4, warn_factor=0.8 → threshold=3.2 → Ok
        $result = $this->makeCheck(readerResult: [0.5, 4])
            ->run($this->createSiteCheck(warnFactor: 0.8, failFactor: 1.5));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsWarnWhenLoadAboveWarnThreshold(): void
    {
        // load=4.0, cpus=4, warn_factor=0.8 → warn=3.2, fail=6.0 → Warn
        $result = $this->makeCheck(readerResult: [4.0, 4])
            ->run($this->createSiteCheck(warnFactor: 0.8, failFactor: 1.5));

        self::assertSame(CheckStatus::Warn, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsFailWhenLoadAboveFailThreshold(): void
    {
        // load=7.0, cpus=4, fail_factor=1.5 → fail=6.0 → Fail
        $result = $this->makeCheck(readerResult: [7.0, 4])
            ->run($this->createSiteCheck(warnFactor: 0.8, failFactor: 1.5));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsWarnAtExactWarnBoundary(): void
    {
        // load=3.2, cpus=4, warn_factor=0.8 → threshold=3.2 → Warn (inclusive >=)
        $result = $this->makeCheck(readerResult: [3.2, 4])
            ->run($this->createSiteCheck(warnFactor: 0.8, failFactor: 1.5));

        self::assertSame(CheckStatus::Warn, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsFailAtExactFailBoundary(): void
    {
        // load=6.0, cpus=4, fail_factor=1.5 → threshold=6.0 → Fail (inclusive >=)
        $result = $this->makeCheck(readerResult: [6.0, 4])
            ->run($this->createSiteCheck(warnFactor: 0.8, failFactor: 1.5));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsUnknownWhenFactorsAreInverted(): void
    {
        $result = $this->makeCheck(readerResult: [1.0, 4])
            ->run($this->createSiteCheck(warnFactor: 1.5, failFactor: 0.8));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('Invalid config', (string) $result->getMessage());
    }

    #[Test]
    public function testRunMessageContainsLoadAndCpuCountOnOk(): void
    {
        $result = $this->makeCheck(readerResult: [1.23, 4])
            ->run($this->createSiteCheck());

        $message = (string) $result->getMessage();
        self::assertStringContainsString('1.23', $message);
        self::assertStringContainsString('4', $message);
    }

    #[Test]
    public function testRunMessageContainsLoadAndCpuCountOnFail(): void
    {
        $result = $this->makeCheck(readerResult: [8.0, 2])
            ->run($this->createSiteCheck(warnFactor: 0.8, failFactor: 1.5));

        $message = (string) $result->getMessage();
        self::assertStringContainsString('8', $message);
        self::assertStringContainsString('2', $message);
    }

    #[Test]
    public function testRunWorksWithSingleCpuSystem(): void
    {
        // load=0.5, cpus=1, warn_factor=0.8 → threshold=0.8 → Ok
        $result = $this->makeCheck(readerResult: [0.5, 1])
            ->run($this->createSiteCheck(warnFactor: 0.8, failFactor: 1.5));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testGetEmailTargetLabelReturnsNull(): void
    {
        self::assertNull($this->makeCheck()->getEmailTargetLabel());
    }

    #[Test]
    public function testResolveEmailTargetReturnsNull(): void
    {
        self::assertNull($this->makeCheck()->resolveEmailTarget([]));
    }

    // --- helpers ---

    /**
     * @param array{0: float, 1: int}|null|string $readerResult
     */
    private function makeCheck(array|null|string $readerResult = [1.0, 4]): LoadAverageCheck
    {
        $reader = $this->createStub(LoadAverageReaderInterface::class);
        $reader->method('read')->willReturn($readerResult);

        return new LoadAverageCheck($reader);
    }

    private function createSiteCheck(float $warnFactor = 0.8, float $failFactor = 1.5): SiteCheck
    {
        $client = new Client();
        $client->setName('Test');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('load_average');
        $check->setConfig([
            'warn_factor' => $warnFactor,
            'fail_factor' => $failFactor,
        ]);

        return $check;
    }
}
