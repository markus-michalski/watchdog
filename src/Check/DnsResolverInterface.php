<?php

declare(strict_types=1);

namespace App\Check;

interface DnsResolverInterface
{
    /**
     * Resolves a hostname to its IP addresses.
     *
     * Returns a list of IP addresses on success (may be empty if the hostname exists but has no A/AAAA records).
     * Returns an error string on failure (DNS error, network timeout, invalid hostname).
     *
     * @param string|null $resolver Custom resolver IP/host (e.g. "8.8.8.8"), or null for system default.
     *
     * @return string[]|string
     */
    public function resolve(string $hostname, ?string $resolver): array|string;
}
