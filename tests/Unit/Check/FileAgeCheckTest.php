<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\FileAgeCheck;
use App\Entity\Site;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileAgeCheckTest extends TestCase
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
    public function testGetTypeReturnsFileAge(): void
    {
        self::assertSame('file_age', (new FileAgeCheck())->getType());
    }

    #[Test]
    public function testGetLabelReturnsHumanReadableLabel(): void
    {
        self::assertSame('File Age', (new FileAgeCheck())->getLabel());
    }

    #[Test]
    public function testGetDefaultConfigReturnsExpectedDefaults(): void
    {
        $defaults = (new FileAgeCheck())->getDefaultConfig();

        self::assertArrayHasKey('max_age_minutes', $defaults);
        self::assertSame(1440, $defaults['max_age_minutes']);
        self::assertArrayHasKey('warn_age_minutes', $defaults);
        self::assertSame(0, $defaults['warn_age_minutes']);
    }

    #[Test]
    public function testGetConfigSchemaContainsAllFields(): void
    {
        $schema = (new FileAgeCheck())->getConfigSchema();
        $names = array_column($schema, 'name');

        self::assertContains('path', $names);
        self::assertContains('max_age_minutes', $names);
        self::assertContains('warn_age_minutes', $names);
    }

    #[Test]
    public function testRunReturnsOkWhenFileIsRecent(): void
    {
        touch($this->tmpFile);
        $check = $this->createSiteCheck($this->tmpFile, maxAgeMinutes: 60);

        $result = (new FileAgeCheck())->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsOkWhenAgeExactlyAtMaxLimit(): void
    {
        // Boundary: age == max_age_minutes must still be Ok (condition is strictly >)
        touch($this->tmpFile, time() - 3600);
        $check = $this->createSiteCheck($this->tmpFile, maxAgeMinutes: 60);

        $result = (new FileAgeCheck())->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsFailWhenFileDoesNotExist(): void
    {
        $nonExistent = sys_get_temp_dir().'/watchdog_nonexistent_'.uniqid();
        $check = $this->createSiteCheck($nonExistent, maxAgeMinutes: 60);

        $result = (new FileAgeCheck())->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('not found', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenFileIsTooOld(): void
    {
        touch($this->tmpFile, time() - 7200); // 2 hours ago
        $check = $this->createSiteCheck($this->tmpFile, maxAgeMinutes: 60);

        $result = (new FileAgeCheck())->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('120 min', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenPathIsNotConfigured(): void
    {
        $check = $this->createSiteCheck('', maxAgeMinutes: 60);

        $result = (new FileAgeCheck())->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('path', (string) $result->getMessage());
    }

    #[Test]
    public function testRunMessageContainsAgeInMinutesOnFail(): void
    {
        touch($this->tmpFile, time() - 3600); // 60 min ago
        $check = $this->createSiteCheck($this->tmpFile, maxAgeMinutes: 30);

        $result = (new FileAgeCheck())->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('60 min', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsUnknownWhenMtimeIsInFuture(): void
    {
        touch($this->tmpFile, time() + 600); // 10 min in the future (clock-skew)
        $check = $this->createSiteCheck($this->tmpFile, maxAgeMinutes: 60);

        $result = (new FileAgeCheck())->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('future', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsWarnWhenFileOlderThanWarnThreshold(): void
    {
        touch($this->tmpFile, time() - 3600); // 60 min ago
        $check = $this->createSiteCheckWithWarn($this->tmpFile, maxAgeMinutes: 120, warnAgeMinutes: 30);

        $result = (new FileAgeCheck())->run($check);

        self::assertSame(CheckStatus::Warn, $result->getStatus());
        self::assertStringContainsString('60 min', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsOkWhenFileFresherThanWarnThreshold(): void
    {
        touch($this->tmpFile); // just touched
        $check = $this->createSiteCheckWithWarn($this->tmpFile, maxAgeMinutes: 120, warnAgeMinutes: 30);

        $result = (new FileAgeCheck())->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsFailEvenWhenWarnThresholdConfigured(): void
    {
        touch($this->tmpFile, time() - 7200); // 120 min ago, past max
        $check = $this->createSiteCheckWithWarn($this->tmpFile, maxAgeMinutes: 60, warnAgeMinutes: 30);

        $result = (new FileAgeCheck())->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
    }

    private function createSiteCheck(string $path, int $maxAgeMinutes): SiteCheck
    {
        return $this->createSiteCheckWithWarn($path, $maxAgeMinutes, 0);
    }

    private function createSiteCheckWithWarn(string $path, int $maxAgeMinutes, int $warnAgeMinutes): SiteCheck
    {
        $site = new Site();
        $site->setName('Example');
        $site->setUrl('https://example.test');

        $check = new SiteCheck();
        $check->setSite($site);
        $check->setType('file_age');
        $check->setConfig([
            'path' => $path,
            'max_age_minutes' => $maxAgeMinutes,
            'warn_age_minutes' => $warnAgeMinutes,
        ]);

        return $check;
    }
}
