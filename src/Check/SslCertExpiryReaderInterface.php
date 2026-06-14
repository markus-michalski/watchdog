<?php

declare(strict_types=1);

namespace App\Check;

interface SslCertExpiryReaderInterface
{
    /**
     * Returns the certificate expiry as a Unix timestamp on success,
     * or a human-readable error string on failure.
     */
    public function read(string $host, int $port, int $timeout, bool $allowSelfSigned): int|string;
}
