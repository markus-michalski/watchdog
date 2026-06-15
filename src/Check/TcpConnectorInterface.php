<?php

declare(strict_types=1);

namespace App\Check;

interface TcpConnectorInterface
{
    /**
     * Attempts a TCP connection to host:port within the given timeout.
     * Returns null on success, or a human-readable error string on failure.
     */
    public function connect(string $host, int $port, int $timeout): ?string;
}
