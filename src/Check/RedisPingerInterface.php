<?php

declare(strict_types=1);

namespace App\Check;

interface RedisPingerInterface
{
    /**
     * Connects to Redis, sends PING, expects +PONG.
     * Returns null on success.
     * Returns an error string on failure (connection error, timeout, unexpected response).
     */
    public function ping(string $host, int $port, int $timeout): ?string;
}
