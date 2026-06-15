<?php

declare(strict_types=1);

namespace App\Check;

final class TcpConnector implements TcpConnectorInterface
{
    public function connect(string $host, int $port, int $timeout): ?string
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

        fclose($socket);

        return null;
    }
}
