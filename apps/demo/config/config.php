<?php

declare(strict_types=1);

use Monolog\Logger;
use TuxWeb\Pyroshape\Core\Exceptions\ConfigNotValidException;

return [
    // Resolve environment values at runtime so PHP-DI's compiled cache contains no credentials.
    'settings' => static function (): array {
        $environment = getenv('PYROSHAPE_ENVIRONMENT') ?: 'production';
        $databasePassword = getenv('PYROSHAPE_DB_PASSWORD');
        $readSecret = static function (string $name): string {
            $value = getenv($name);
            if ($value === false || strlen($value) < 32) {
                throw new ConfigNotValidException("$name must contain at least 32 bytes");
            }

            return $value;
        };

        return [
            'environment' => $environment,
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
                'dsn' => getenv('PYROSHAPE_DB_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=pyroshape_demo;charset=utf8mb4',
                'username' => getenv('PYROSHAPE_DB_USER') ?: null,
                'password' => $databasePassword === false ? null : $databasePassword,
                'options' => [],
            ],
            'jwt_refresh_token' => [
                'key' => $readSecret('PYROSHAPE_JWT_REFRESH_KEY'),
                'algorithm' => 'HS256',
                'iss' => 'pyroshape',
                'time_skew' => 30,
                'expire' => 7776000,
                'auto_refresh' => 1814400,
            ],
            'jwt_access_token' => [
                'key' => $readSecret('PYROSHAPE_JWT_ACCESS_KEY'),
                'algorithm' => 'HS256',
                'iss' => 'pyroshape',
                'time_skew' => 7,
                'expire' => 600,
            ],
            'login_cookie' => [
                'cookiename' => '_pyroshape_login',
                'cookieopts' => [
                    'path' => '/',
                    'secure' => $environment !== 'development',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ],
                'key' => $readSecret('PYROSHAPE_COOKIE_KEY'),
                'salt' => $readSecret('PYROSHAPE_COOKIE_SALT'),
            ],
            'emailVerification' => [
                'expire' => 86400,
                'devExposeToken' => $environment === 'development',
            ],
        ];
    },
];
