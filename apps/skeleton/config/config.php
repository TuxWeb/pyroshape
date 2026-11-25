<?php

declare(strict_types=1);

use Monolog\Logger;

return [
    // Read process settings at runtime so PHP-DI's compiled cache contains no secrets.
    'settings' => static function (): array {
        $runtimeEnvironment = getenv('PYROSHAPE_ENVIRONMENT') ?: 'production';

        return [
            'environment' => $runtimeEnvironment,
            'displayErrorDetails' => false,
            'basePath' => '',
            'logger' => [
                'name' => 'Pyroshape',
                'path' => __DIR__ . '/../logs/app.log',
                'level' => Logger::DEBUG,
                'dateFormat' => 'Y-m-d H:i:s',
                'logFormat' => "%datetime% [%channel%.%level_name% %extra%] %message% %context%\n",
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
                'dsn' => (string) getenv('PYROSHAPE_DB_DSN'),
                'username' => (string) getenv('PYROSHAPE_DB_USER'),
                'password' => (string) getenv('PYROSHAPE_DB_PASSWORD'),
                'options' => [],
            ],
            'jwt_refresh_token' => [
                'key' => (string) getenv('PYROSHAPE_JWT_REFRESH_KEY'),
                'algorithm' => 'HS256',
                'iss' => 'pyroshape',
                'time_skew' => 30,
                'expire' => 7776000,
                'auto_refresh' => 1814400,
            ],
            'jwt_access_token' => [
                'key' => (string) getenv('PYROSHAPE_JWT_ACCESS_KEY'),
                'algorithm' => 'HS256',
                'iss' => 'pyroshape',
                'time_skew' => 7,
                'expire' => 600,
            ],
            'login_cookie' => [
                'cookiename' => '_pyroshape_login',
                'cookieopts' => [
                    'path' => '/',
                    'secure' => $runtimeEnvironment !== 'development',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ],
                'key' => (string) getenv('PYROSHAPE_COOKIE_KEY'),
                'salt' => (string) getenv('PYROSHAPE_COOKIE_SALT'),
            ],
            'emailVerification' => [
                'expire' => 86400,
                'devExposeToken' => $runtimeEnvironment === 'development',
            ],
        ];
    },
];
