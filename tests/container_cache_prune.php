<?php

/*
 * Copyright (c) 2026 TuxWeb
 * Author: Dino Ciuffetti <dino@tuxweb.it>, TuxWeb S.r.l. - https://www.tuxweb.it/
 * Date: 2026-11-26
 * SPDX-License-Identifier: MIT
 *
 * Permission is granted to use, modify, and distribute this software under
 * the MIT License. Keep the copyright and license notices with copies or
 * substantial portions. This software is provided as is, without warranty.
 * See LICENSE for the full terms.
 */

declare(strict_types=1);

use TuxWeb\Pyroshape\Core\App;
use Monolog\Logger;

require __DIR__ . '/../vendor/autoload.php';

$fixtureRoot = __DIR__ . '/../tmp/container-cache-fixture';
removeDir($fixtureRoot);
mkdir($fixtureRoot . '/config', 0775, true);
mkdir($fixtureRoot . '/logs', 0775, true);
mkdir($fixtureRoot . '/public/app', 0775, true);
file_put_contents($fixtureRoot . '/public/app/index.html', '<!doctype html><html><body>Pyroshape</body></html>');

$configA = writeConfig($fixtureRoot, 'a', 'PyroshapeCacheA');
$configB = writeConfig($fixtureRoot, 'b', 'PyroshapeCacheB');
$nestedStaleCache = $fixtureRoot . '/tmp/tmp/container/stale-cache';
mkdir($nestedStaleCache, 0775, true);
file_put_contents($nestedStaleCache . '/CompiledContainer.php', '<?php');

new App($configA);
new App($configB);

$cacheRoot = $fixtureRoot . '/tmp/container';
$cacheDirs = is_dir($cacheRoot)
    ? array_values(array_filter(glob($cacheRoot . '/*') ?: [], 'is_dir'))
    : [];
$nestedStaleExists = is_dir($nestedStaleCache);

removeDir($fixtureRoot);

if (count($cacheDirs) !== 1) {
    fwrite(STDERR, 'Expected exactly one active container cache dir, got ' . count($cacheDirs) . "\n");
    exit(1);
}

if ($nestedStaleExists) {
    fwrite(STDERR, "Expected nested stale container cache dir to be removed\n");
    exit(1);
}

echo "container cache prune ok\n";

function writeConfig(string $fixtureRoot, string $suffix, string $loggerName): string
{
    $key = str_repeat($suffix, 64);
    $config = [
        'settings' => [
            'environment' => 'development',
            'displayErrorDetails' => false,
            'basePath' => '',
            'logger' => [
                'name' => $loggerName,
                'path' => $fixtureRoot . '/logs/app-' . $suffix . '.log',
                'level' => Logger::DEBUG,
                'dateFormat' => 'Y-m-d H:i:s',
                'logFormat' => "%datetime% [%channel%.%level_name% %extra%] %message% %context%\n",
                'timezone' => 'Europe/Rome',
            ],
            'accessLogger' => [
                'name' => $loggerName,
                'path' => $fixtureRoot . '/logs/access-' . $suffix . '.log',
                'level' => Logger::INFO,
                'dateFormat' => 'Y-m-d H:i:s',
                'logFormat' => "%datetime% %message%\n",
                'timezone' => 'Europe/Rome',
            ],
            'headers' => [
                'X-Pyroshape' => '1',
            ],
            'frontend' => [
                'appPath' => $fixtureRoot . '/public/app',
            ],
            'database' => [
                'dsn' => 'sqlite::memory:',
                'username' => '',
                'password' => '',
                'options' => [],
            ],
            'jwt_refresh_token' => [
                'key' => $key,
                'algorithm' => 'HS256',
                'iss' => 'pyroshape',
                'time_skew' => 30,
                'expire' => 7776000,
                'auto_refresh' => 1814400,
            ],
            'jwt_access_token' => [
                'key' => strrev($key),
                'algorithm' => 'HS256',
                'iss' => 'pyroshape',
                'time_skew' => 7,
                'expire' => 600,
            ],
            'login_cookie' => [
                'cookiename' => '_pyroshape_login',
                'cookieopts' => [
                    'path' => '/',
                    'secure' => false,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ],
                'key' => hash('sha256', 'cookie-' . $suffix),
                'salt' => hash('sha256', 'salt-' . $suffix),
            ],
            'emailVerification' => [
                'expire' => 86400,
                'devExposeToken' => true,
            ],
        ],
    ];

    $path = $fixtureRoot . '/config/config-' . $suffix . '.php';
    file_put_contents($path, '<?php return ' . var_export($config, true) . ';');

    return $path;
}

function removeDir(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir((string) $item->getPathname()) : unlink((string) $item->getPathname());
    }

    rmdir($path);
}
