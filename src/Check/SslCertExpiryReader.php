<?php

declare(strict_types=1);

namespace App\Check;

final class SslCertExpiryReader implements SslCertExpiryReaderInterface
{
    public function read(string $host, int $port, int $timeout, bool $allowSelfSigned): int|string
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => !$allowSelfSigned,
                'allow_self_signed' => $allowSelfSigned,
            ],
        ]);

        $socket = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (false === $socket) {
            $detail = ('' !== (string) $errstr) ? (string) $errstr : "error code {$errno}";

            return sprintf('Connection to %s:%d failed: %s', $host, $port, $detail);
        }

        $params = stream_context_get_params($socket);
        fclose($socket);

        $sslOptions = $params['options']['ssl'] ?? null;
        if (!is_array($sslOptions)) {
            return 'No SSL options in stream context after handshake';
        }

        $cert = $sslOptions['peer_certificate'] ?? null;
        if (!$cert instanceof \OpenSSLCertificate) {
            return 'No peer certificate captured';
        }

        $info = openssl_x509_parse($cert);
        if (!is_array($info)) {
            return 'Failed to parse certificate';
        }

        $validTo = $info['validTo_time_t'] ?? null;
        if (!is_int($validTo)) {
            return 'Certificate has no validTo_time_t field';
        }

        return $validTo;
    }
}
