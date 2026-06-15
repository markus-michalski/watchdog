<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\RedisCheck;
use App\Check\RedisPingerInterface;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RedisCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsRedis(): void
    {
        self::assertSame('redis', $this->makeCheck()->getType());
    }

    #[Test]
    public function testGetLabelReturnsHumanReadableLabel(): void
    {
        self::assertSame('Redis', $this->makeCheck()->getLabel());
    }

    #[Test]
    public function testGetDefaultConfigReturnsExpectedDefaults(): void
    {
        $defaults = $this->makeCheck()->getDefaultConfig();

        self::assertSame('', $defaults['host']);
        self::assertSame(6379, $defaults['port']);
        self::assertSame(3, $defaults['timeout']);
    }

    #[Test]
    public function testGetConfigSchemaContainsAllFields(): void
    {
        $names = array_column($this->makeCheck()->getConfigSchema(), 'name');

        self::assertContains('host', $names);
        self::assertContains('port', $names);
        self::assertContains('timeout', $names);
    }

    #[Test]
    public function testGetConfigSchemaMarksHostAsRequired(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $byName = array_column($schema, null, 'name');

        self::assertTrue($byName['host']['required']);
        self::assertFalse($byName['port']['required']);
        self::assertFalse($byName['timeout']['required']);
    }

    #[Test]
    public function testRunReturnsUnknownWhenHostNotConfigured(): void
    {
        $result = $this->makeCheck()->run($this->createSiteCheck('', 6379));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('host', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsUnknownWhenPortIsZero(): void
    {
        $result = $this->makeCheck()->run($this->createSiteCheck('redis.internal', 0));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('port', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsUnknownWhenPortIsOutOfRange(): void
    {
        $result = $this->makeCheck()->run($this->createSiteCheck('redis.internal', 99999));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsOkWhenPingSucceeds(): void
    {
        $result = $this->makeCheck(pingerResult: null)->run($this->createSiteCheck('redis.internal', 6379));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertStringContainsString('PONG', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenPingFails(): void
    {
        $error = 'Connection to redis.internal:6379 failed: Connection refused';
        $result = $this->makeCheck(pingerResult: $error)->run($this->createSiteCheck('redis.internal', 6379));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Connection refused', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailOnUnexpectedResponse(): void
    {
        $error = 'Unexpected response from redis.internal:6379: -ERR';
        $result = $this->makeCheck(pingerResult: $error)->run($this->createSiteCheck('redis.internal', 6379));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('-ERR', (string) $result->getMessage());
    }

    #[Test]
    public function testRunPassesCorrectParamsToPinger(): void
    {
        $pinger = $this->createMock(RedisPingerInterface::class);
        $pinger->expects(self::once())
            ->method('ping')
            ->with('redis.internal', 6379, 5)
            ->willReturn(null);

        (new RedisCheck($pinger))->run($this->createSiteCheck('redis.internal', 6379, timeout: 5));
    }

    #[Test]
    public function testRunClampsTimeoutBetweenOneAndSixty(): void
    {
        $pinger = $this->createMock(RedisPingerInterface::class);
        $pinger->expects(self::exactly(2))
            ->method('ping')
            ->willReturnCallback(function (string $host, int $port, int $timeout): ?string {
                self::assertGreaterThanOrEqual(1, $timeout);
                self::assertLessThanOrEqual(60, $timeout);

                return null;
            });

        $check = new RedisCheck($pinger);
        $check->run($this->createSiteCheck('redis.internal', 6379, timeout: 0));
        $check->run($this->createSiteCheck('redis.internal', 6379, timeout: 300));
    }

    #[Test]
    public function testGetEmailTargetLabelReturnsHostPort(): void
    {
        self::assertSame('Host:Port', $this->makeCheck()->getEmailTargetLabel());
    }

    #[Test]
    public function testResolveEmailTargetReturnsHostAndPort(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['host' => 'redis.internal', 'port' => 6379]);

        self::assertSame('redis.internal:6379', $result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenHostMissing(): void
    {
        self::assertNull($this->makeCheck()->resolveEmailTarget(['port' => 6379]));
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenHostEmpty(): void
    {
        self::assertNull($this->makeCheck()->resolveEmailTarget(['host' => '', 'port' => 6379]));
    }

    // --- helpers ---

    private function makeCheck(?string $pingerResult = null): RedisCheck
    {
        $pinger = $this->createStub(RedisPingerInterface::class);
        $pinger->method('ping')->willReturn($pingerResult);

        return new RedisCheck($pinger);
    }

    private function createSiteCheck(string $host, int $port, int $timeout = 3): SiteCheck
    {
        $client = new Client();
        $client->setName('Test');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('redis');
        $check->setConfig([
            'host' => $host,
            'port' => $port,
            'timeout' => $timeout,
        ]);

        return $check;
    }
}
