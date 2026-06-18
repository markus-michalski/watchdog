<?php

declare(strict_types=1);

namespace App\Tests\Unit\Check;

use App\Check\DnsResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DnsResolverTest extends TestCase
{
    private DnsResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DnsResolver();
    }

    #[Test]
    public function rejectsNonIpResolver(): void
    {
        $result = $this->resolver->resolve('example.com', 'not-an-ip');

        $this->assertIsString($result);
        $this->assertStringContainsString('must be a valid IP address', $result);
        $this->assertStringContainsString('not-an-ip', $result);
    }

    #[Test]
    public function rejectsHostnameAsResolver(): void
    {
        $result = $this->resolver->resolve('example.com', 'dns.google');

        $this->assertIsString($result);
        $this->assertStringContainsString('must be a valid IP address', $result);
    }

    /** @param non-empty-string $privateIp */
    #[Test]
    #[DataProvider('privateAndReservedIps')]
    public function rejectsPrivateAndReservedResolvers(string $privateIp): void
    {
        $result = $this->resolver->resolve('example.com', $privateIp);

        $this->assertIsString($result);
        $this->assertStringContainsString('private or reserved', $result);
        $this->assertStringContainsString($privateIp, $result);
    }

    /** @return array<string, array{0: non-empty-string}> */
    public static function privateAndReservedIps(): array
    {
        return [
            'loopback'            => ['127.0.0.1'],
            'RFC1918 class A'     => ['10.0.0.1'],
            'RFC1918 class B'     => ['172.16.0.1'],
            'RFC1918 class B top' => ['172.31.255.255'],
            'RFC1918 class C'     => ['192.168.1.1'],
            'link-local'          => ['169.254.0.1'],
        ];
    }

    #[Test]
    public function acceptsPublicIpAsResolver(): void
    {
        // 8.8.8.8 is a valid public IP — the validation itself should pass.
        // The actual dig call will either succeed or fail depending on network;
        // we only verify that no validation error string is returned immediately.
        $result = $this->resolver->resolve('example.com', '8.8.8.8');

        // Validation errors are returned as strings starting with 'Invalid' or containing 'private'.
        if (is_string($result)) {
            $this->assertStringNotContainsString('must be a valid IP address', $result);
            $this->assertStringNotContainsString('private or reserved', $result);
        }
        // Array return means validation passed and dig ran — also correct.
    }
}
