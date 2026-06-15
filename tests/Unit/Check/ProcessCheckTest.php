<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\ProcessCheck;
use App\Check\ProcessCheckerInterface;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProcessCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsProcess(): void
    {
        self::assertSame('process', $this->makeCheck()->getType());
    }

    #[Test]
    public function testGetLabelReturnsHumanReadableLabel(): void
    {
        self::assertSame('Process Running', $this->makeCheck()->getLabel());
    }

    #[Test]
    public function testGetDefaultConfigContainsProcessName(): void
    {
        $defaults = $this->makeCheck()->getDefaultConfig();

        self::assertArrayHasKey('process_name', $defaults);
        self::assertSame('', $defaults['process_name']);
    }

    #[Test]
    public function testGetConfigSchemaMarksProcessNameAsRequired(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $field = $schema[0];

        self::assertSame('process_name', $field['name']);
        self::assertTrue($field['required']);
        self::assertSame('text', $field['type']);
    }

    #[Test]
    public function testRunReturnsUnknownWhenNoProcessNameConfigured(): void
    {
        $result = $this->makeCheck()->run($this->createSiteCheck(''));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('No process name', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsOkWhenProcessIsRunning(): void
    {
        $result = $this->makeCheck(running: true)->run($this->createSiteCheck('nginx'));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertStringContainsString('nginx', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenProcessIsNotRunning(): void
    {
        $result = $this->makeCheck(running: false)->run($this->createSiteCheck('nginx'));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('nginx', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsUnknownWhenCheckerReturnsErrorString(): void
    {
        $result = $this->makeCheck(running: 'pgrep exited with code 127 — is pgrep available on this system?')
            ->run($this->createSiteCheck('nginx'));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('pgrep', (string) $result->getMessage());
    }

    #[Test]
    public function testRunPassesProcessNameToChecker(): void
    {
        $checker = $this->createMock(ProcessCheckerInterface::class);
        $checker->expects(self::once())
            ->method('isRunning')
            ->with('php-fpm')
            ->willReturn(true);

        $check = new ProcessCheck($checker);
        $check->run($this->createSiteCheck('php-fpm'));
    }

    #[Test]
    public function testRunTrimsWhitespaceFromProcessName(): void
    {
        $checker = $this->createMock(ProcessCheckerInterface::class);
        $checker->expects(self::once())
            ->method('isRunning')
            ->with('nginx')
            ->willReturn(true);

        $check = new ProcessCheck($checker);
        $check->run($this->createSiteCheck('  nginx  '));
    }

    #[Test]
    public function testGetEmailTargetLabelReturnsProcess(): void
    {
        self::assertSame('Process', $this->makeCheck()->getEmailTargetLabel());
    }

    #[Test]
    public function testResolveEmailTargetReturnsProcessName(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['process_name' => 'nginx']);

        self::assertSame('nginx', $result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenNotConfigured(): void
    {
        self::assertNull($this->makeCheck()->resolveEmailTarget([]));
        self::assertNull($this->makeCheck()->resolveEmailTarget(['process_name' => '']));
    }

    // --- helpers ---

    private function makeCheck(bool|string $running = true): ProcessCheck
    {
        $checker = $this->createStub(ProcessCheckerInterface::class);
        $checker->method('isRunning')->willReturn($running);

        return new ProcessCheck($checker);
    }

    private function createSiteCheck(string $processName): SiteCheck
    {
        $client = new Client();
        $client->setName('Test');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('process');
        $check->setConfig(['process_name' => $processName]);

        return $check;
    }
}
