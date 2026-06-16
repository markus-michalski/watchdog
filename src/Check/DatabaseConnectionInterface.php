<?php

declare(strict_types=1);

namespace App\Check;

interface DatabaseConnectionInterface
{
    /**
     * Attempts to open a database connection via PDO DSN.
     * Returns null on success.
     * Returns an error string on failure (connection refused, auth error, timeout, invalid DSN).
     */
    public function connect(string $dsn, string $username, string $password, int $timeout): ?string;
}
