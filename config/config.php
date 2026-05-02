<?php

declare(strict_types=1);

use Monolog\Logger;

return [
    'settings' => [
        'environment' => 'development',
        'displayErrorDetails' => false,
        'basePath' => '',
        'logger' => [
            'name' => 'Pyroshape',
            'path' => __DIR__ . '/../logs/app.log',
            'level' => Logger::DEBUG,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% [%channel%.%level_name% %extra%] %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'accessLogger' => [
            'name' => 'Pyroshape',
            'path' => __DIR__ . '/../logs/access.log',
            'level' => Logger::INFO,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'headers' => [
            'X-Pyroshape' => '1',
            'X-Robots-Tag' => 'noindex, nofollow',
        ],
        'frontend' => [
            'appPath' => __DIR__ . '/../public/app',
        ],
        'database' => [
            'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=pyroshape;charset=utf8mb4',
            'username' => 'pyroshape',
            'password' => '',
            'options' => [],
        ],
        'jwt_refresh_token' => [
            'key' => 'change-me-refresh-key',
            'algorithm' => 'HS256',
            'iss' => 'pyroshape',
            'time_skew' => 30,
            'expire' => 7776000,
            'auto_refresh' => 1814400,
        ],
        'jwt_access_token' => [
            'key' => 'change-me-access-key',
            'algorithm' => 'HS256',
            'iss' => 'pyroshape',
            'time_skew' => 7,
            'expire' => 600,
        ],
        'login_cookie' => [
            'cookiename' => '_pyroshape_login',
            'cookieopts' => [
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ],
            'key' => 'change-me-cookie-key',
            'salt' => 'change-me-cookie-salt',
        ],
        'emailVerification' => [
            'expire' => 86400,
            'devExposeToken' => true,
        ],
    ],
];
