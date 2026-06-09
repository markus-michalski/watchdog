<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    // Force test env regardless of what the container environment provides
    putenv('APP_ENV=test');
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'test';

    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? true) {
    umask(0000);
}
