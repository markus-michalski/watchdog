<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\FileSizeCheck;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileSizeCheckTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'watchdog_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    #[Test]
    public function testGetTypeReturnsFileSize(): void
    {
        self::assertSame('file_size', (new FileSizeCheck())->getType());
    }

    #[Test]
    public function testGetLabelReturnsHumanReadableLabel(): void
    {
        self::assertSame('File Size', (new FileSizeCheck())->getLabel());
    }

    #[Test]
    public function testGetDefaultConfigReturnsExpectedDefaults(): void
    {
        $defaults = (new FileSizeCheck())->getDefaultConfig();

        self::assertSame('', $defaults['path']);
        self::assertSame(0, $defaults['min_bytes']);
        self::assertSame(0, $defaults['max_bytes']);
    }

    #[Test]
    public function testGetConfigSchemaContainsAllFields(): void
    {
        $names = array_column((new FileSizeCheck())->getConfigSchema(), 'name');

        self::assertContains('path', $names);
        self::assertContains('min_bytes', $names);
        self::assertContains('max_bytes', $names);
    }

    #[Test]
    public function testGetConfigSchemaMarksPathAsRequired(): void
    {
        $schema = (new FileSizeCheck())->getConfigSchema();
        $byName = array_column($schema, null, 'name');

        self::assertTrue($byName['path']['required']);
        self::assertFalse($byName['min_bytes']['required']);
        self::assertFalse($byName['max_bytes']['required']);
    }

    #[Test]
    public function testRunReturnsUnknownWhenPathNotConfigured(): void
    {
        $result = (new FileSizeCheck())->run($this->createSiteCheck(path: ''));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('path', strtolower((string) $result->getMessage()));
    }

    #[Test]
    public function testRunReturnsFailWhenFileDoesNotExist(): void
    {
        $nonExistent = sys_get_temp_dir() . '/watchdog_nonexistent_' . uniqid();
        $result = (new FileSizeCheck())->run($this->createSiteCheck(path: $nonExistent));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('not found', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsOkWhenFileExistsAndNoBoundsConfigured(): void
    {
        file_put_contents($this->tmpFile, 'hello');
        $result = (new FileSizeCheck())->run($this->createSiteCheck());

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsOkForEmptyFileWhenNoBoundsConfigured(): void
    {
        // Empty file is valid when no min/max configured
        file_put_contents($this->tmpFile, '');
        $result = (new FileSizeCheck())->run($this->createSiteCheck());

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsOkWhenFileSizeWithinBounds(): void
    {
        file_put_contents($this->tmpFile, str_repeat('x', 500));
        $result = (new FileSizeCheck())->run($this->createSiteCheck(minBytes: 100, maxBytes: 1000));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsFailWhenFileSizeBelowMinBytes(): void
    {
        file_put_contents($this->tmpFile, str_repeat('x', 50));
        $result = (new FileSizeCheck())->run($this->createSiteCheck(minBytes: 100));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('50', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenFileSizeAboveMaxBytes(): void
    {
        file_put_contents($this->tmpFile, str_repeat('x', 2000));
        $result = (new FileSizeCheck())->run($this->createSiteCheck(maxBytes: 1000));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('2000', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsOkWhenFileSizeExactlyAtMinBoundary(): void
    {
        file_put_contents($this->tmpFile, str_repeat('x', 100));
        $result = (new FileSizeCheck())->run($this->createSiteCheck(minBytes: 100));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsOkWhenFileSizeExactlyAtMaxBoundary(): void
    {
        file_put_contents($this->tmpFile, str_repeat('x', 1000));
        $result = (new FileSizeCheck())->run($this->createSiteCheck(maxBytes: 1000));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunDetectsEmptyBackupFile(): void
    {
        // Primary use case: detect "Backup ist 0 Bytes"
        file_put_contents($this->tmpFile, '');
        $result = (new FileSizeCheck())->run($this->createSiteCheck(minBytes: 1));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsUnknownWhenMinExceedsMax(): void
    {
        file_put_contents($this->tmpFile, str_repeat('x', 500));
        $result = (new FileSizeCheck())->run($this->createSiteCheck(minBytes: 1000, maxBytes: 100));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('Invalid config', (string) $result->getMessage());
    }

    #[Test]
    public function testRunMessageContainsActualFileSizeOnOk(): void
    {
        file_put_contents($this->tmpFile, str_repeat('x', 1234));
        $result = (new FileSizeCheck())->run($this->createSiteCheck());

        self::assertStringContainsString('1234', (string) $result->getMessage());
    }

    #[Test]
    public function testGetEmailTargetLabelReturnsPath(): void
    {
        self::assertSame('Path', (new FileSizeCheck())->getEmailTargetLabel());
    }

    #[Test]
    public function testResolveEmailTargetReturnsPath(): void
    {
        $result = (new FileSizeCheck())->resolveEmailTarget(['path' => '/var/log/app.log']);

        self::assertSame('/var/log/app.log', $result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenPathMissing(): void
    {
        self::assertNull((new FileSizeCheck())->resolveEmailTarget([]));
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenPathEmpty(): void
    {
        self::assertNull((new FileSizeCheck())->resolveEmailTarget(['path' => '']));
    }

    // --- helpers ---

    private function createSiteCheck(
        ?string $path = null,
        int $minBytes = 0,
        int $maxBytes = 0,
    ): SiteCheck {
        $client = new Client();
        $client->setName('Test');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('file_size');
        $check->setConfig([
            'path' => $path ?? $this->tmpFile,
            'min_bytes' => $minBytes,
            'max_bytes' => $maxBytes,
        ]);

        return $check;
    }
}
