<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\LogFileCheck;
use App\Check\LogFileReaderInterface;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use App\Enum\RunnerMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LogFileCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsLogFile(): void
    {
        self::assertSame('log_file', $this->makeCheck()->getType());
    }

    #[Test]
    public function testGetLabelReturnsHumanReadableLabel(): void
    {
        self::assertSame('Log File', $this->makeCheck()->getLabel());
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

        self::assertSame('', $defaults['log_path']);
        self::assertSame('', $defaults['pattern']);
        self::assertSame(1440, $defaults['max_age_minutes']);
    }

    #[Test]
    public function testGetConfigSchemaContainsAllFields(): void
    {
        $names = array_column($this->makeCheck()->getConfigSchema(), 'name');

        self::assertContains('log_path', $names);
        self::assertContains('pattern', $names);
        self::assertContains('max_age_minutes', $names);
    }

    #[Test]
    public function testGetConfigSchemaMarksRequiredFields(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $byName = array_column($schema, null, 'name');

        self::assertTrue($byName['log_path']['required']);
        self::assertTrue($byName['pattern']['required']);
        self::assertFalse($byName['max_age_minutes']['required']);
    }

    #[Test]
    public function testRunReturnsUnknownWhenPathNotConfigured(): void
    {
        $result = $this->makeCheck()->run($this->createSiteCheck(logPath: ''));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('log_path', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsUnknownWhenPatternNotConfigured(): void
    {
        $result = $this->makeCheck()->run($this->createSiteCheck(pattern: ''));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('pattern', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenFileDoesNotExist(): void
    {
        // reader returns null = file not found
        $result = $this->makeCheck(readerResult: null)
            ->run($this->createSiteCheck());

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('not found', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenFileReadFails(): void
    {
        $result = $this->makeCheck(readerResult: 'Permission denied: /var/log/app.log')
            ->run($this->createSiteCheck());

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Permission denied', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsOkWhenPatternFoundInRecentFile(): void
    {
        $readerResult = [
            'mtime' => time() - 60,
            'lines' => ['[2024-01-01 03:00] Backup completed successfully'],
        ];

        $result = $this->makeCheck(readerResult: $readerResult)
            ->run($this->createSiteCheck(pattern: 'Backup completed', maxAgeMinutes: 1440));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsFailWhenPatternNotFoundInRecentFile(): void
    {
        $readerResult = [
            'mtime' => time() - 60,
            'lines' => ['[2024-01-01 03:00] Backup started', '[2024-01-01 03:05] ERROR: disk full'],
        ];

        $result = $this->makeCheck(readerResult: $readerResult)
            ->run($this->createSiteCheck(pattern: 'Backup completed', maxAgeMinutes: 1440));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('pattern', strtolower((string) $result->getMessage()));
    }

    #[Test]
    public function testRunReturnsFailWhenFileIsTooOld(): void
    {
        $readerResult = [
            'mtime' => time() - 7200, // 120 minutes ago
            'lines' => ['[2024-01-01 03:00] Backup completed successfully'],
        ];

        $result = $this->makeCheck(readerResult: $readerResult)
            ->run($this->createSiteCheck(pattern: 'Backup completed', maxAgeMinutes: 60));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('120', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsOkWhenFileAgeExactlyAtMaxLimit(): void
    {
        // Boundary: age == max_age_minutes must still be Ok (strictly >)
        $readerResult = [
            'mtime' => time() - 3600, // exactly 60 minutes ago
            'lines' => ['[2024-01-01] SUCCESS'],
        ];

        $result = $this->makeCheck(readerResult: $readerResult)
            ->run($this->createSiteCheck(pattern: 'SUCCESS', maxAgeMinutes: 60));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunSupportsRegexPattern(): void
    {
        $readerResult = [
            'mtime' => time() - 60,
            'lines' => ['2024-01-01 03:00:00 | status=OK | duration=42s'],
        ];

        $result = $this->makeCheck(readerResult: $readerResult)
            ->run($this->createSiteCheck(pattern: '/status=OK.*duration=\d+s/'));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsUnknownForInvalidRegex(): void
    {
        $readerResult = [
            'mtime' => time() - 60,
            'lines' => ['some log line'],
        ];

        $result = $this->makeCheck(readerResult: $readerResult)
            ->run($this->createSiteCheck(pattern: '/[invalid regex/'));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('Invalid pattern', (string) $result->getMessage());
    }

    #[Test]
    public function testRunPassesLogPathToReader(): void
    {
        $reader = $this->createMock(LogFileReaderInterface::class);
        $reader->expects(self::once())
            ->method('read')
            ->with('/var/log/app.log')
            ->willReturn(['mtime' => time() - 60, 'lines' => ['SUCCESS']]);

        (new LogFileCheck($reader))->run(
            $this->createSiteCheck(logPath: '/var/log/app.log', pattern: 'SUCCESS')
        );
    }

    #[Test]
    public function testGetEmailTargetLabelReturnsLogPath(): void
    {
        self::assertSame('Log path', $this->makeCheck()->getEmailTargetLabel());
    }

    #[Test]
    public function testResolveEmailTargetReturnsLogPath(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['log_path' => '/var/log/app.log']);

        self::assertSame('/var/log/app.log', $result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenLogPathMissing(): void
    {
        self::assertNull($this->makeCheck()->resolveEmailTarget([]));
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenLogPathEmpty(): void
    {
        self::assertNull($this->makeCheck()->resolveEmailTarget(['log_path' => '']));
    }

    // --- helpers ---

    /**
     * @param array{mtime: int, lines: string[]}|null|string $readerResult
     */
    private function makeCheck(array|null|string $readerResult = ['mtime' => 0, 'lines' => []]): LogFileCheck
    {
        $reader = $this->createStub(LogFileReaderInterface::class);
        $reader->method('read')->willReturn($readerResult);

        return new LogFileCheck($reader);
    }

    private function createSiteCheck(
        string $logPath = '/var/log/app.log',
        string $pattern = 'SUCCESS',
        int $maxAgeMinutes = 1440,
    ): SiteCheck {
        $client = new Client();
        $client->setName('Test');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('log_file');
        $check->setConfig([
            'log_path' => $logPath,
            'pattern' => $pattern,
            'max_age_minutes' => $maxAgeMinutes,
        ]);

        return $check;
    }
}
