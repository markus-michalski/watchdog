<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\DnsCheck;
use App\Check\DnsResolverInterface;
use App\Entity\Client;
use App\Entity\SiteCheck;
use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DnsCheckTest extends TestCase
{
    #[Test]
    public function testGetTypeReturnsDns(): void
    {
        self::assertSame('dns', $this->makeCheck()->getType());
    }

    #[Test]
    public function testGetLabelReturnsHumanReadableLabel(): void
    {
        self::assertSame('DNS', $this->makeCheck()->getLabel());
    }

    #[Test]
    public function testGetDefaultConfigReturnsExpectedDefaults(): void
    {
        $defaults = $this->makeCheck()->getDefaultConfig();

        self::assertSame('', $defaults['hostname']);
        self::assertSame('', $defaults['expected_ip']);
        self::assertSame('', $defaults['resolver']);
    }

    #[Test]
    public function testGetConfigSchemaContainsAllFields(): void
    {
        $names = array_column($this->makeCheck()->getConfigSchema(), 'name');

        self::assertContains('hostname', $names);
        self::assertContains('expected_ip', $names);
        self::assertContains('resolver', $names);
    }

    #[Test]
    public function testGetConfigSchemaMarksHostnameAsRequired(): void
    {
        $schema = $this->makeCheck()->getConfigSchema();
        $byName = array_column($schema, null, 'name');

        self::assertTrue($byName['hostname']['required']);
        self::assertFalse($byName['expected_ip']['required']);
        self::assertFalse($byName['resolver']['required']);
    }

    #[Test]
    public function testRunReturnsUnknownWhenHostnameNotConfigured(): void
    {
        $result = $this->makeCheck()->run($this->createSiteCheck(hostname: ''));

        self::assertSame(CheckStatus::Unknown, $result->getStatus());
        self::assertStringContainsString('hostname', strtolower((string) $result->getMessage()));
    }

    #[Test]
    public function testRunReturnsOkWhenHostnameResolvesAndNoExpectedIpConfigured(): void
    {
        $result = $this->makeCheck(resolverResult: ['1.2.3.4', '5.6.7.8'])
            ->run($this->createSiteCheck(hostname: 'example.com'));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsOkWhenExpectedIpFoundInResults(): void
    {
        $result = $this->makeCheck(resolverResult: ['1.2.3.4', '5.6.7.8'])
            ->run($this->createSiteCheck(hostname: 'example.com', expectedIp: '5.6.7.8'));

        self::assertSame(CheckStatus::Ok, $result->getStatus());
    }

    #[Test]
    public function testRunReturnsFailWhenExpectedIpNotInResults(): void
    {
        $result = $this->makeCheck(resolverResult: ['1.2.3.4'])
            ->run($this->createSiteCheck(hostname: 'example.com', expectedIp: '9.9.9.9'));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('9.9.9.9', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenResolutionFails(): void
    {
        $error = 'DNS resolution failed for example.com: Name or service not known';
        $result = $this->makeCheck(resolverResult: $error)
            ->run($this->createSiteCheck(hostname: 'example.com'));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('Name or service not known', (string) $result->getMessage());
    }

    #[Test]
    public function testRunReturnsFailWhenNoIpsReturned(): void
    {
        // Hostname resolved but returned no A/AAAA records
        $result = $this->makeCheck(resolverResult: [])
            ->run($this->createSiteCheck(hostname: 'example.com'));

        self::assertSame(CheckStatus::Fail, $result->getStatus());
        self::assertStringContainsString('no records', strtolower((string) $result->getMessage()));
    }

    #[Test]
    public function testRunMessageContainsResolvedIpsOnOk(): void
    {
        $result = $this->makeCheck(resolverResult: ['1.2.3.4', '5.6.7.8'])
            ->run($this->createSiteCheck(hostname: 'example.com'));

        $message = (string) $result->getMessage();
        self::assertStringContainsString('1.2.3.4', $message);
        self::assertStringContainsString('5.6.7.8', $message);
    }

    #[Test]
    public function testRunPassesHostnameAndNullResolverWhenNotConfigured(): void
    {
        $resolver = $this->createMock(DnsResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with('example.com', null)
            ->willReturn(['1.2.3.4']);

        (new DnsCheck($resolver))->run($this->createSiteCheck(hostname: 'example.com'));
    }

    #[Test]
    public function testRunPassesCustomResolverWhenConfigured(): void
    {
        $resolver = $this->createMock(DnsResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with('example.com', '8.8.8.8')
            ->willReturn(['1.2.3.4']);

        (new DnsCheck($resolver))->run(
            $this->createSiteCheck(hostname: 'example.com', resolver: '8.8.8.8')
        );
    }

    #[Test]
    public function testGetEmailTargetLabelReturnsHostname(): void
    {
        self::assertSame('Hostname', $this->makeCheck()->getEmailTargetLabel());
    }

    #[Test]
    public function testResolveEmailTargetReturnsHostname(): void
    {
        $result = $this->makeCheck()->resolveEmailTarget(['hostname' => 'example.com']);

        self::assertSame('example.com', $result);
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenHostnameMissing(): void
    {
        self::assertNull($this->makeCheck()->resolveEmailTarget([]));
    }

    #[Test]
    public function testResolveEmailTargetReturnsNullWhenHostnameEmpty(): void
    {
        self::assertNull($this->makeCheck()->resolveEmailTarget(['hostname' => '']));
    }

    // --- helpers ---

    /**
     * @param string[]|string $resolverResult
     */
    private function makeCheck(array|string $resolverResult = ['1.2.3.4']): DnsCheck
    {
        $resolver = $this->createStub(DnsResolverInterface::class);
        $resolver->method('resolve')->willReturn($resolverResult);

        return new DnsCheck($resolver);
    }

    private function createSiteCheck(
        string $hostname = 'example.com',
        string $expectedIp = '',
        string $resolver = '',
    ): SiteCheck {
        $client = new Client();
        $client->setName('Test');

        $check = new SiteCheck();
        $check->setClient($client);
        $check->setType('dns');
        $check->setConfig([
            'hostname' => $hostname,
            'expected_ip' => $expectedIp,
            'resolver' => $resolver,
        ]);

        return $check;
    }
}
