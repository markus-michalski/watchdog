<?php

declare(strict_types=1);

namespace App\Check;

final class DatabaseConnector implements DatabaseConnectionInterface
{
    public function connect(string $dsn, string $username, string $password, int $timeout): ?string
    {
        try {
            new \PDO(
                $dsn,
                '' !== $username ? $username : null,
                '' !== $password ? $password : null,
                [
                    \PDO::ATTR_TIMEOUT => $timeout,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ],
            );

            return null;
        } catch (\PDOException $e) {
            return sprintf('Database connection failed: %s', $e->getMessage());
        }
    }
}
