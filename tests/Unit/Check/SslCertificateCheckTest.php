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

        self::assertSame([], $defaults['hosts']);
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

        self::assertContains('hosts', $names);
        self::assertContains('port', $names);
        self::assertContains('warn_days', $names);
        self::assertContains('fail_days', $names);
        self::assertContains('timeout', $names);
        self::assertContains('allow_self_signed', $names);
    }

    #[Test]
    public function testGetConfigSchemaMarksHostsAsRequired(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $field = $this->findField($schema, 'hosts');

        self::assertTrue($field['required']);
        self::assertSame('client_url_multiselect', $field['type']);
    }

    #[Test]
    public function testRunReturnsUnknownWhenNoHostsConfigured(): void
    {
        $check = $this->createSiteCheck(hosts: []);

        $result = $this->makeCheck()->run($check);

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('host', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenConnectionFails(): void
    {
        $errorMessage = 'Connection to unreachable.example.com:443 failed: Network unreachable';
        $check = $this->createSiteCheck(hosts: ['unreachable.example.com']);

        $result = $this->makeCheck(readerResult: $errorMessage)->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('unreachable.example.com', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailMessageFromReader(): void
    {
        $check = $this->createSiteCheck(hosts: ['example.com']);

        $result = $this->makeCheck(readerResult: 'Connection to example.com:443 failed: Connection timed out')->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Connection timed out', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsOkWhenCertIsValidAndNotExpiringSoon(): void
    {
        $expiry = time() + (60 * 86400); // 60 days from now
        $check = $this->createSiteCheck(hosts: ['example.com']);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertStringContainsString('60', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsWarnWhenCertExpiresSoon(): void
    {
        $expiry = time() + (10 * 86400); // 10 days — inside default warn_days=14
        $check = $this->createSiteCheck(hosts: ['example.com']);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Warn, $result->getStatus());
        self::assertStringContainsString('10', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenCertExpiresWithinFailDays(): void
    {
        $expiry = time() + (2 * 86400); // 2 days — inside default fail_days=3
        $check = $this->createSiteCheck(hosts: ['example.com']);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('2', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenCertIsAlreadyExpired(): void
    {
        $expiry = time() - (5 * 86400); // expired 5 days ago
        $check = $this->createSiteCheck(hosts: ['example.com']);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('expired', (string) $result->getMessage());
    }

    #[Test]
    public function testRunRespectsCustomWarnDays(): void
    {
        $expiry = time() + (20 * 86400); // 20 days — inside custom warn_days=30
        $check = $this->createSiteCheck(hosts: ['example.com'], warnDays: 30, failDays: 3);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Warn, $result->getStatus());
    }

    #[Test]
    public function testRunRespectsCustomFailDays(): void
    {
        $expiry = time() + (5 * 86400); // 5 days — inside custom fail_days=7
        $check = $this->createSiteCheck(hosts: ['example.com'], warnDays: 14, failDays: 7);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsWarnAtExactWarnBoundary(): void
    {
        // exactly warn_days away: must be Warn (condition is <=, inclusive)
        $expiry = time() + (14 * 86400);
        $check = $this->createSiteCheck(hosts: ['example.com'], warnDays: 14, failDays: 3);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Warn, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsFailAtExactFailBoundary(): void
    {
        // exactly fail_days away: must be Fail (condition is <=, inclusive)
        $expiry = time() + (3 * 86400);
        $check = $this->createSiteCheck(hosts: ['example.com'], warnDays: 14, failDays: 3);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
    }

    #[Test]
    public function testRunPassesAllowSelfSignedFlagToReader(): void
    {
        $expiry = time() + (30 * 86400);
        $check = $this->createSiteCheck(hosts: ['internal.example.com'], allowSelfSigned: true);

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
    public function testRunChecksMultipleHostsAndReturnsWorstCaseStatus(): void
    {
        // host1 is fine, host2 is expiring soon → worst case = Warn
        $goodExpiry = time() + (60 * 86400);
        $warnExpiry = time() + (7 * 86400); // inside warn_days=14

        $check = $this->createSiteCheck(hosts: ['ok.example.com', 'warn.example.com']);

        $reader = $this->createStub(SslCertExpiryReaderInterface::class);
        $reader->method('read')->willReturnCallback(function (string $host) use ($goodExpiry, $warnExpiry): int {
            return match ($host) {
                'ok.example.com' => $goodExpiry,
                'warn.example.com' => $warnExpiry,
                default => $goodExpiry,
            };
        });

        $result = (new SslCertificateCheck($reader))->run($check);

        self::assertSame(CheckStatus::Warn, $result->getStatus());
    }

    #[Test]
    public function testRunFailsEntireCheckIfOneHostFails(): void
    {
        $goodExpiry = time() + (60 * 86400);
        $failExpiry = time() + (1 * 86400); // inside fail_days=3

        $check = $this->createSiteCheck(hosts: ['ok.example.com', 'fail.example.com']);

        $reader = $this->createStub(SslCertExpiryReaderInterface::class);
        $reader->method('read')->willReturnCallback(function (string $host) use ($goodExpiry, $failExpiry): int {
            return match ($host) {
                'ok.example.com' => $goodExpiry,
                'fail.example.com' => $failExpiry,
                default => $goodExpiry,
            };
        });

        $result = (new SslCertificateCheck($reader))->run($check);

        self::assertSame(CheckStatus::Fail, $result->getStatus());
    }

    #[Test]
    public function testRunMultiHostMessageDetailsProblemHostsAndSummarizesOk(): void
    {
        $goodExpiry = time() + (60 * 86400);
        $warnExpiry = time() + (7 * 86400);

        $check = $this->createSiteCheck(hosts: ['alpha.example.com', 'beta.example.com']);

        $reader = $this->createStub(SslCertExpiryReaderInterface::class);
        $reader->method('read')->willReturnCallback(function (string $host) use ($goodExpiry, $warnExpiry): int {
            return match ($host) {
                'alpha.example.com' => $goodExpiry,
                'beta.example.com' => $warnExpiry,
                default => $goodExpiry,
            };
        });

        $result = (new SslCertificateCheck($reader))->run($check);
        $message = (string) $result->getMessage();

        // Warn host is named; OK host is only counted
        self::assertStringContainsString('beta.example.com', $message);
        self::assertStringContainsString('1 OK', $message);
        self::assertStringNotContainsString('alpha.example.com', $message);
    }

    #[Test]
    public function testRunMultiHostAllOkProducesCompactSummary(): void
    {
        $expiry = time() + (36 * 86400);
        $check = $this->createSiteCheck(hosts: ['a.example.com', 'b.example.com', 'c.example.com']);

        $result = $this->makeCheck(readerResult: $expiry)->run($check);
        $message = (string) $result->getMessage();

        self::assertSame(CheckStatus::Ok, $result->getStatus());
        self::assertStringContainsString('3 hosts', $message);
        self::assertStringContainsString('all OK', $message);
        self::assertStringContainsString('min. 36d', $message);
    }

    #[Test]
    public function testGetEmailTargetLabelReturnsHosts(): void
    {
        self::assertSame('Hosts', $this->makeCheck()->getEmailTargetLabel());
    }

    #[Test]
    public function testResolveEmailTargetReturnsSingleHost(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['hosts' => ['example.com']]);

        self::assertSame('example.com', $result);
    }

    #[Test]
    public function testResolveEmailTargetJoinsMultipleHosts(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['hosts' => ['a.example.com', 'b.example.com']]);

        self::assertSame('a.example.com, b.example.com', $result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenHostsEmpty(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['hosts' => []]);

        self::assertNull($result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenHostsMissing(): void
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

    /**
     * @param list<string>           $hosts
     */
    private function createSiteCheck(
        array $hosts,
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
            'hosts' => $hosts,
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
