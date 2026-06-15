<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\TcpConnectorInterface;
use App\Check\TcpPortCheck;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TcpPortCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsTcpPort(): void
    {
        self::assertSame('tcp_port', $this->makeCheck()->getType());
    }

    #[Test]
    public function testGetLabelReturnsHumanReadableLabel(): void
    {
        self::assertSame('TCP Port', $this->makeCheck()->getLabel());
    }

    #[Test]
    public function testGetDefaultConfigReturnsExpectedDefaults(): void
    {
        $defaults = $this->makeCheck()->getDefaultConfig();

        self::assertSame('', $defaults['host']);
        self::assertSame(80, $defaults['port']);
        self::assertSame(5, $defaults['timeout']);
    }

    #[Test]
    public function testGetConfigSchemaContainsAllFields(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $names = array_column($schema, 'name');

        self::assertContains('host', $names);
        self::assertContains('port', $names);
        self::assertContains('timeout', $names);
    }

    #[Test]
    public function testGetConfigSchemaMarksHostAndPortAsRequired(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $byName = array_column($schema, null, 'name');

        self::assertTrue($byName['host']['required']);
        self::assertTrue($byName['port']['required']);
        self::assertFalse($byName['timeout']['required']);
    }

    #[Test]
    public function testRunReturnsOkWhenConnectionSucceeds(): void
    {
        $check = $this->createSiteCheck('db.internal', 3306);

        $result = $this->makeCheck(connectorResult: null)->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertStringContainsString('db.internal', (string) $result->getMessage());
        self::assertStringContainsString('3306', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenConnectionFails(): void
    {
        $error = 'Connection to db.internal:3306 failed: Connection refused';
        $check = $this->createSiteCheck('db.internal', 3306);

        $result = $this->makeCheck(connectorResult: $error)->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Connection refused', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsUnknownWhenHostNotConfigured(): void
    {
        $check = $this->createSiteCheck('', 3306);

        $result = $this->makeCheck()->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('host', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsUnknownWhenPortIsZero(): void
    {
        $check = $this->createSiteCheck('db.internal', 0);

        $result = $this->makeCheck()->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('port', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsUnknownWhenPortIsOutOfRange(): void
    {
        $check = $this->createSiteCheck('db.internal', 99999);

        $result = $this->makeCheck()->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
    }

    #[Test]
    public function testRunPassesCorrectParamsToConnector(): void
    {
        $connector = $this->createMock(TcpConnectorInterface::class);
        $connector->expects(self::once())
            ->method('connect')
            ->with('redis.internal', 6379, 10)
            ->willReturn(null);

        $check = $this->createSiteCheck('redis.internal', 6379, timeout: 10);
        $result = (new TcpPortCheck($connector))->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunFloorsTimeoutToOneWhenZeroConfigured(): void
    {
        $connector = $this->createMock(TcpConnectorInterface::class);
        $connector->expects(self::once())
            ->method('connect')
            ->with('host.example.com', 80, 1) // floored from 0 to 1
            ->willReturn(null);

        $check = $this->createSiteCheck('host.example.com', 80, timeout: 0);
        (new TcpPortCheck($connector))->run($check);
    }

    #[Test]
    public function testRunCapsTimeoutAtSixtySeconds(): void
    {
        $connector = $this->createMock(TcpConnectorInterface::class);
        $connector->expects(self::once())
            ->method('connect')
            ->with('host.example.com', 80, 60) // capped from 300 to 60
            ->willReturn(null);

        $check = $this->createSiteCheck('host.example.com', 80, timeout: 300);
        (new TcpPortCheck($connector))->run($check);
    }

    #[Test]
    public function testRunUsesDefaultTimeoutWhenNotConfigured(): void
    {
        $connector = $this->createMock(TcpConnectorInterface::class);
        $connector->expects(self::once())
            ->method('connect')
            ->with('host.example.com', 80, 5)
            ->willReturn(null);

        $check = $this->createSiteCheck('host.example.com', 80);
        (new TcpPortCheck($connector))->run($check);
    }

    #[Test]
    public function testRunFailMessageComesDirectlyFromConnector(): void
    {
        $error = 'Connection to 10.0.0.1:5432 failed: Network unreachable';
        $check = $this->createSiteCheck('10.0.0.1', 5432);

        $result = $this->makeCheck(connectorResult: $error)->run($check);

        self::assertSame($error, $result->getMessage());
    }

    #[Test]
    public function testGetEmailTargetLabelReturnsHostPort(): void
    {
        self::assertSame('Host:Port', $this->makeCheck()->getEmailTargetLabel());
    }

    #[Test]
    public function testResolveEmailTargetReturnsHostAndPort(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['host' => 'db.internal', 'port' => 3306]);

        self::assertSame('db.internal:3306', $result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenHostMissing(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['port' => 3306]);

        self::assertNull($result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenPortMissing(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['host' => 'db.internal']);

        self::assertNull($result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullForEmptyHost(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['host' => '', 'port' => 3306]);

        self::assertNull($result);
    }

    // --- helpers ---

    private function makeCheck(?string $connectorResult = null): TcpPortCheck
    {
        $connector = $this->createStub(TcpConnectorInterface::class);
        $connector->method('connect')->willReturn($connectorResult);

        return new TcpPortCheck($connector);
    }

    private function createSiteCheck(string $host, int $port, int $timeout = 5): SiteCheck
    {
        $client = new Client();
        $client->setName('Test');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('tcp_port');
        $check->setConfig([
            'host' => $host,
            'port' => $port,
            'timeout' => $timeout,
        ]);

        return $check;
    }
}
