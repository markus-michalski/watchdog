<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    // Force test env regardless of what the container environment provides
    putenv('APP_ENV=test');
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'test';

    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? true) {
    umask(0o000);
}

// Create test database schema if it does not exist yet
$dbUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? '';
if (str_starts_with($dbUrl, 'sqlite:')) {
    $dbFile = preg_replace('#^sqlite:////+#', '/', $dbUrl) ?: '';
    if ($dbFile && !file_exists($dbFile)) {
        @mkdir(dirname($dbFile), 0o777, true);
        passthru(sprintf(
            'php %s/bin/console doctrine:schema:create --env=test --no-interaction -q',
            dirname(__DIR__)
        ));
    }
}
