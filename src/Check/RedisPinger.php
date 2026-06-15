<?php

declare(strict_types=1);

namespace App\Check;

final class RedisPinger implements RedisPingerInterface
{
    public function ping(string $host, int $port, int $timeout): ?string
    {
        try {
            $socket = @stream_socket_client(
                "tcp://{$host}:{$port}",
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
            );
        } catch (\Throwable $e) {
            return sprintf('Connection to %s:%d failed: %s', $host, $port, $e->getMessage());
        }

        if (false === $socket) {
            $detail = ('' !== (string) $errstr) ? (string) $errstr : "error code {$errno}";

            return sprintf('Connection to %s:%d failed: %s', $host, $port, $detail);
        }

        stream_set_timeout($socket, $timeout);
        fwrite($socket, "PING\r\n");
        $response = fgets($socket, 128);
        fclose($socket);

        if (false === $response) {
            return sprintf('No response received from %s:%d', $host, $port);
        }

        $response = trim($response);
        if ('+PONG' !== $response) {
            return sprintf('Unexpected response from %s:%d: %s', $host, $port, $response);
        }

        return null;
    }
}
