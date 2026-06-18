<?php

declare(strict_types=1);

namespace App\Check;

final class DnsResolver implements DnsResolverInterface
{
    public function resolve(string $hostname, ?string $resolver): array|string
    {
        // Custom resolver requires dig; fall back to system resolver via gethostbynamel
        if (null !== $resolver) {
            return $this->resolveWithDig($hostname, $resolver);
        }

        $ips = @gethostbynamel($hostname);
        if (false === $ips) {
            return sprintf('DNS resolution failed for %s: Name or service not known', $hostname);
        }

        return $ips;
    }

    /** @return string[]|string */
    private function resolveWithDig(string $hostname, string $resolver): array|string
    {
        if (!filter_var($resolver, FILTER_VALIDATE_IP)) {
            return sprintf('Invalid resolver "%s": must be a valid IP address', $resolver);
        }

        if (false === filter_var($resolver, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return sprintf('Resolver "%s" is a private or reserved IP address and is not allowed', $resolver);
        }

        $cmd = sprintf(
            'dig +short +time=5 @%s %s A 2>&1',
            escapeshellarg($resolver),
            escapeshellarg($hostname),
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if (0 !== $exitCode) {
            return sprintf('DNS resolution via %s failed for %s (exit %d)', $resolver, $hostname, $exitCode);
        }

        return array_values(array_filter($output, fn (string $line) => (bool) filter_var(trim($line), FILTER_VALIDATE_IP)));
    }
}
