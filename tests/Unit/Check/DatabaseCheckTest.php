<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\DatabaseCheck;
use App\Check\DatabaseConnectionInterface;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DatabaseCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsDatabase(): void
    {
        self::assertSame('database', $this->makeCheck()->getType());
    }

    #[Test]
    public function testGetLabelReturnsHumanReadableLabel(): void
    {
        self::assertSame('Database', $this->makeCheck()->getLabel());
    }

    #[Test]
    public function testGetDefaultConfigReturnsExpectedDefaults(): void
    {
        $defaults = $this->makeCheck()->getDefaultConfig();

        self::assertSame('', $defaults['dsn']);
        self::assertSame('', $defaults['username']);
        self::assertSame('', $defaults['password']);
        self::assertSame(5, $defaults['timeout']);
    }

    #[Test]
    public function testGetConfigSchemaContainsAllFields(): void
    {
        $names = array_column($this->makeCheck()->getConfigSchema(), 'name');

        self::assertContains('dsn', $names);
        self::assertContains('username', $names);
        self::assertContains('password', $names);
        self::assertContains('timeout', $names);
    }

    #[Test]
    public function testGetConfigSchemaMarksDsnAsRequired(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $byName = array_column($schema, null, 'name');

        self::assertTrue($byName['dsn']['required']);
        self::assertFalse($byName['username']['required']);
        self::assertFalse($byName['password']['required']);
        self::assertFalse($byName['timeout']['required']);
    }

    #[Test]
    public function testGetConfigSchemaPasswordFieldHasPasswordType(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $byName = array_column($schema, null, 'name');

        self::assertSame('password', $byName['password']['type']);
    }

    #[Test]
    public function testRunReturnsUnknownWhenDsnNotConfigured(): void
    {
        $result = $this->makeCheck()->run($this->createSiteCheck(dsn: ''));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('DSN', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsUnknownWhenDsnIsWhitespaceOnly(): void
    {
        $result = $this->makeCheck()->run($this->createSiteCheck(dsn: '   '));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsOkWhenConnectionSucceeds(): void
    {
        $result = $this->makeCheck(connectorResult: null)
            ->run($this->createSiteCheck());

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunMessageContainsDsnOnSuccess(): void
    {
        $result = $this->makeCheck(connectorResult: null)
            ->run($this->createSiteCheck(dsn: 'mysql:host=db.internal;dbname=app'));

        self::assertStringContainsString('mysql:host=db.internal;dbname=app', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenConnectionFails(): void
    {
        $error = 'Database connection failed: SQLSTATE[HY000] [2002] Connection refused';
        $result = $this->makeCheck(connectorResult: $error)
            ->run($this->createSiteCheck());

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Connection refused', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenAuthenticationFails(): void
    {
        $error = 'Database connection failed: SQLSTATE[28000] [1045] Access denied for user';
        $result = $this->makeCheck(connectorResult: $error)
            ->run($this->createSiteCheck());

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Access denied', (string) $result->getMessage());
    }

    #[Test]
    public function testRunPassesCorrectParamsToConnector(): void
    {
        $connector = $this->createMock(DatabaseConnectionInterface::class);
        $connector->expects(self::once())
            ->method('connect')
            ->with('pgsql:host=pg.internal;dbname=shop', 'reader', 'secret', 5)
            ->willReturn(null);

        (new DatabaseCheck($connector))->run(
            $this->createSiteCheck(
                dsn: 'pgsql:host=pg.internal;dbname=shop',
                username: 'reader',
                password: 'secret',
                timeout: 5,
            )
        );
    }

    #[Test]
    public function testRunClampsTimeoutBetweenOneAndThirty(): void
    {
        $connector = $this->createMock(DatabaseConnectionInterface::class);
        $connector->expects(self::exactly(2))
            ->method('connect')
            ->willReturnCallback(function (string $dsn, string $username, string $password, int $timeout): ?string {
                self::assertGreaterThanOrEqual(1, $timeout);
                self::assertLessThanOrEqual(30, $timeout);

                return null;
            });

        $check = new DatabaseCheck($connector);
        $check->run($this->createSiteCheck(timeout: 0));
        $check->run($this->createSiteCheck(timeout: 999));
    }

    #[Test]
    public function testRunWorksWithSqliteWithoutCredentials(): void
    {
        $connector = $this->createMock(DatabaseConnectionInterface::class);
        $connector->expects(self::once())
            ->method('connect')
            ->with('sqlite::memory:', '', '', self::anything())
            ->willReturn(null);

        (new DatabaseCheck($connector))->run(
            $this->createSiteCheck(dsn: 'sqlite::memory:', username: '', password: '')
        );
    }

    #[Test]
    public function testGetEmailTargetLabelReturnsDsn(): void
    {
        self::assertSame('DSN', $this->makeCheck()->getEmailTargetLabel());
    }

    #[Test]
    public function testResolveEmailTargetReturnsDsn(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['dsn' => 'mysql:host=localhost;dbname=app']);

        self::assertSame('mysql:host=localhost;dbname=app', $result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenDsnMissing(): void
    {
        self::assertNull($this->makeCheck()->resolveEmailTarget([]));
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenDsnEmpty(): void
    {
        self::assertNull($this->makeCheck()->resolveEmailTarget(['dsn' => '']));
    }

    // --- helpers ---

    private function makeCheck(?string $connectorResult = null): DatabaseCheck
    {
        $connector = $this->createStub(DatabaseConnectionInterface::class);
        $connector->method('connect')->willReturn($connectorResult);

        return new DatabaseCheck($connector);
    }

    private function createSiteCheck(
        string $dsn = 'mysql:host=localhost;dbname=app',
        string $username = 'monitor',
        string $password = '',
        int $timeout = 5,
    ): SiteCheck {
        $client = new Client();
        $client->setName('Test');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('database');
        $check->setConfig([
            'dsn' => $dsn,
            'username' => $username,
            'password' => $password,
            'timeout' => $timeout,
        ]);

        return $check;
    }
}
