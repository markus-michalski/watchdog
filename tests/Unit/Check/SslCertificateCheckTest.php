<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\SslCertExpiryReaderInterface;
use App\Check\SslCertificateCheck;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SslCertificateCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsSslCert(): void
    {
        self::assertSame('ssl_cert', $this->makeCheck()->getType());
    }

    #[Test]
    public function testGetLabelReturnsHumanReadableLabel(): void
    {
        self::assertSame('SSL Certificate', $this->makeCheck()->getLabel());
    }

    #[Test]
    public function testGetDefaultConfigReturnsExpectedDefaults(): void
    {
        $defaults = $this->makeCheck()->getDefaultConfig();

        self::assertSame(443, $defaults['port']);
        self::assertSame(14, $defaults['warn_days']);
        self::assertSame(3, $defaults['fail_days']);
        self::assertSame(10, $defaults['timeout']);
        self::assertFalse($defaults['allow_self_signed']);
    }

    #[Test]
    public function testGetConfigSchemaContainsAllFields(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $names = array_column($schema, 'name');

        self::assertContains('host', $names);
        self::assertContains('port', $names);
        self::assertContains('warn_days', $names);
        self::assertContains('fail_days', $names);
        self::assertContains('timeout', $names);
        self::assertContains('allow_self_signed', $names);
    }

    #[Test]
    public function testGetConfigSchemaMarksHostAsRequired(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $field = $this->findField($schema, 'host');

        self::assertTrue($field['required']);
    }

    #[Test]
    public function testRunReturnsUnknownWhenHostNotConfigured(): void
    {
        $check = $this->createSiteCheck(host: '');

        $result = $this->makeCheck()->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('host', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenConnectionFails(): void
    {
        $errorMessage = 'Connection to unreachable.example.com:443 failed: Network unreachable';
        $check = $this->createSiteCheck(host: 'unreachable.example.com');

        $result = $this->makeCheck(readerResult: $errorMessage)->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('unreachable.example.com', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailMessageFromReader(): void
    {
        $check = $this->createSiteCheck(host: 'example.com');

        $result = $this->makeCheck(readerResult: 'Connection to example.com:443 failed: Connection timed out')->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Connection timed out', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsOkWhenCertIsValidAndNotExpiringSoon(): void
    {
        $expiry = time() + (60 * 86400); // 60 days from now
        $check = $this->createSiteCheck(host: 'example.com');

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertStringContainsString('60', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsWarnWhenCertExpiresSoon(): void
    {
        $expiry = time() + (10 * 86400); // 10 days — inside default warn_days=14
        $check = $this->createSiteCheck(host: 'example.com');

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Warn, $result->getStatus());
        self::assertStringContainsString('10', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenCertExpiresWithinFailDays(): void
    {
        $expiry = time() + (2 * 86400); // 2 days — inside default fail_days=3
        $check = $this->createSiteCheck(host: 'example.com');

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('2', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenCertIsAlreadyExpired(): void
    {
        $expiry = time() - (5 * 86400); // expired 5 days ago
        $check = $this->createSiteCheck(host: 'example.com');

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('expired', (string) $result->getMessage());
    }

    #[Test]
    public function testRunRespectsCustomWarnDays(): void
    {
        $expiry = time() + (20 * 86400); // 20 days — inside custom warn_days=30
        $check = $this->createSiteCheck(host: 'example.com', warnDays: 30, failDays: 3);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Warn, $result->getStatus());
    }

    #[Test]
    public function testRunRespectsCustomFailDays(): void
    {
        $expiry = time() + (5 * 86400); // 5 days — inside custom fail_days=7
        $check = $this->createSiteCheck(host: 'example.com', warnDays: 14, failDays: 7);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsWarnAtExactWarnBoundary(): void
    {
        // exactly warn_days away: must be Warn (condition is <=, inclusive)
        $expiry = time() + (14 * 86400);
        $check = $this->createSiteCheck(host: 'example.com', warnDays: 14, failDays: 3);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Warn, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsFailAtExactFailBoundary(): void
    {
        // exactly fail_days away: must be Fail (condition is <=, inclusive)
        $expiry = time() + (3 * 86400);
        $check = $this->createSiteCheck(host: 'example.com', warnDays: 14, failDays: 3);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
    }

    #[Test]
    public function testRunPassesAllowSelfSignedFlagToReader(): void
    {
        $expiry = time() + (30 * 86400);
        $check = $this->createSiteCheck(host: 'internal.example.com', allowSelfSigned: true);

        /** @var SslCertExpiryReaderInterface&MockObject $reader */
        $reader = $this->createMock(SslCertExpiryReaderInterface::class);
        $reader->expects(self::once())
            ->method('read')
            ->with('internal.example.com', 443, 10, true)
            ->willReturn($expiry);

        $checkInstance = new SslCertificateCheck($reader);
        $result = $checkInstance->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testGetEmailTargetLabelReturnsHost(): void
    {
        self::assertSame('Host', $this->makeCheck()->getEmailTargetLabel());
    }

    #[Test]
    public function testResolveEmailTargetReturnsHost(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['host' => 'example.com']);

        self::assertSame('example.com', $result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenHostEmpty(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['host' => '']);

        self::assertNull($result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenHostMissing(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget([]);

        self::assertNull($result);
    }

    // --- helpers ---

    private function makeCheck(int|string $readerResult = 0): SslCertificateCheck
    {
        $reader = $this->createStub(SslCertExpiryReaderInterface::class);
        $reader->method('read')->willReturn($readerResult);

        return new SslCertificateCheck($reader);
    }

    private function createSiteCheck(
        string $host,
        int $port = 443,
        int $warnDays = 14,
        int $failDays = 3,
        int $timeout = 10,
        bool $allowSelfSigned = false,
    ): SiteCheck {
        $client = new Client();
        $client->setName('Test');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('ssl_cert');
        $check->setConfig([
            'host' => $host,
            'port' => $port,
            'warn_days' => $warnDays,
            'fail_days' => $failDays,
            'timeout' => $timeout,
            'allow_self_signed' => $allowSelfSigned,
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
