<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\DiskSpaceCheck;
use App\Check\DiskSpaceReaderInterface;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DiskSpaceCheckTest extends TestCase
{
    // 1 GB in bytes
    private const GB = 1_073_741_824;

    #[Test]
    public function testGetTypeReturnsDiskSpace(): void
    {
        self::assertSame('disk_space', $this->makeCheck()->getType());
    }

    #[Test]
    public function testGetLabelReturnsHumanReadableLabel(): void
    {
        self::assertSame('Disk Space', $this->makeCheck()->getLabel());
    }

    #[Test]
    public function testGetDefaultConfigReturnsExpectedDefaults(): void
    {
        $defaults = $this->makeCheck()->getDefaultConfig();

        self::assertSame('/', $defaults['path']);
        self::assertSame(80, $defaults['warn_percent']);
        self::assertSame(90, $defaults['fail_percent']);
    }

    #[Test]
    public function testGetConfigSchemaContainsAllFields(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $names = array_column($schema, 'name');

        self::assertContains('path', $names);
        self::assertContains('warn_percent', $names);
        self::assertContains('fail_percent', $names);
    }

    #[Test]
    public function testGetConfigSchemaMarksPathAsRequired(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $field = $this->findField($schema, 'path');

        self::assertTrue($field['required']);
        self::assertSame('text', $field['type']);
    }

    #[Test]
    public function testRunReturnsUnknownWhenPathNotConfigured(): void
    {
        $check = $this->createSiteCheck(path: '');

        $result = $this->makeCheck()->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('path', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsOkWhenDiskUsageBelowWarnThreshold(): void
    {
        // 70 GB used of 100 GB = 70%
        $total = 100 * self::GB;
        $free = 30 * self::GB;
        $check = $this->createSiteCheck(warnPercent: 80, failPercent: 90);

        $result = $this->makeCheck(readerResult: [$total, $free])->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertStringContainsString('70', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsWarnWhenDiskUsageAboveWarnThreshold(): void
    {
        // 85 GB used of 100 GB = 85%
        $total = 100 * self::GB;
        $free = 15 * self::GB;
        $check = $this->createSiteCheck(warnPercent: 80, failPercent: 90);

        $result = $this->makeCheck(readerResult: [$total, $free])->run($check);

        self::assertSame(CheckStatus::Warn, $result->getStatus());
        self::assertStringContainsString('85', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenDiskUsageAboveFailThreshold(): void
    {
        // 95 GB used of 100 GB = 95%
        $total = 100 * self::GB;
        $free = 5 * self::GB;
        $check = $this->createSiteCheck(warnPercent: 80, failPercent: 90);

        $result = $this->makeCheck(readerResult: [$total, $free])->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('95', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsWarnAtExactWarnBoundary(): void
    {
        // exactly 80% used with warn_percent=80 → Warn (inclusive)
        $total = 100 * self::GB;
        $free = 20 * self::GB;
        $check = $this->createSiteCheck(warnPercent: 80, failPercent: 90);

        $result = $this->makeCheck(readerResult: [$total, $free])->run($check);

        self::assertSame(CheckStatus::Warn, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsFailAtExactFailBoundary(): void
    {
        // exactly 90% used with fail_percent=90 → Fail (inclusive)
        $total = 100 * self::GB;
        $free = 10 * self::GB;
        $check = $this->createSiteCheck(warnPercent: 80, failPercent: 90);

        $result = $this->makeCheck(readerResult: [$total, $free])->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsUnknownWhenReaderReturnsNull(): void
    {
        // null = path does not exist or is not a directory (configuration error, not a monitoring failure)
        $check = $this->createSiteCheck(path: '/nonexistent');

        $result = $this->makeCheck(readerResult: null)->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('not found', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenReaderReturnsErrorString(): void
    {
        // string = OS-level read error (disk_total_space / disk_free_space failed)
        $check = $this->createSiteCheck();

        $result = $this->makeCheck(readerResult: 'Cannot read disk space for path: /')->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Cannot read', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsUnknownWhenThresholdsAreInverted(): void
    {
        $check = $this->createSiteCheck(warnPercent: 90, failPercent: 80);

        $result = $this->makeCheck()->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('Invalid config', (string) $result->getMessage());
    }

    #[Test]
    public function testRunRespectsCustomThresholds(): void
    {
        // 75% used — below custom warn=70 is NOT true, above custom warn=70 → Warn
        $total = 100 * self::GB;
        $free = 25 * self::GB;
        $check = $this->createSiteCheck(warnPercent: 70, failPercent: 85);

        $result = $this->makeCheck(readerResult: [$total, $free])->run($check);

        self::assertSame(CheckStatus::Warn, $result->getStatus());
    }

    #[Test]
    public function testRunMessageContainsFreeSpaceInfo(): void
    {
        $total = 100 * self::GB;
        $free = 30 * self::GB;
        $check = $this->createSiteCheck();

        $result = $this->makeCheck(readerResult: [$total, $free])->run($check);

        // Message should contain both the percentage and free space
        $message = (string) $result->getMessage();
        self::assertStringContainsString('70', $message);
        self::assertStringContainsString('GB', $message);
    }

    #[Test]
    public function testRunPassesConfiguredPathToReader(): void
    {
        $reader = $this->createMock(DiskSpaceReaderInterface::class);
        $reader->expects(self::once())
            ->method('read')
            ->with('/mnt/data')
            ->willReturn([100 * self::GB, 50 * self::GB]);

        $check = $this->createSiteCheck(path: '/mnt/data');
        $checkInstance = new DiskSpaceCheck($reader);
        $checkInstance->run($check);
    }

    #[Test]
    public function testRunReturnsUnknownWhenTotalSpaceIsZero(): void
    {
        // Protect against division by zero
        $check = $this->createSiteCheck();

        $result = $this->makeCheck(readerResult: [0, 0])->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
    }

    #[Test]
    public function testGetEmailTargetLabelReturnsPath(): void
    {
        self::assertSame('Path', $this->makeCheck()->getEmailTargetLabel());
    }

    #[Test]
    public function testResolveEmailTargetReturnsPathFromConfig(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['path' => '/var/data']);

        self::assertSame('/var/data', $result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenPathMissing(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget([]);

        self::assertNull($result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenPathEmpty(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['path' => '']);

        self::assertNull($result);
    }

    // --- helpers ---

    /**
     * @param array{0: int, 1: int}|null|string $readerResult
     */
    private function makeCheck(array|null|string $readerResult = [100 * self::GB, 50 * self::GB]): DiskSpaceCheck
    {
        $reader = $this->createStub(DiskSpaceReaderInterface::class);
        $reader->method('read')->willReturn($readerResult);

        return new DiskSpaceCheck($reader);
    }

    private function createSiteCheck(
        string $path = '/',
        int $warnPercent = 80,
        int $failPercent = 90,
    ): SiteCheck {
        $client = new Client();
        $client->setName('Test');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('disk_space');
        $check->setConfig([
            'path' => $path,
            'warn_percent' => $warnPercent,
            'fail_percent' => $failPercent,
        ]);

        return $check;
    }

    /**
     * @param array<int, array<string, mixed>> $schema
     * @return array<string, mixed>
     */
    private function findField(array $schema, string $name): array
    {
        foreach ($schema as $field) {
            if ($field['name'] === $name) {
                return $field;
            }
        }

        throw new \LogicException(sprintf('Field "%s" not found in config schema.', $name));
    }
}
