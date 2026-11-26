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

use TuxWeb\PyroshapeSkeleton\App;
use Monolog\Logger;
use Slim\Psr7\Factory\ServerRequestFactory;

require __DIR__ . '/../vendor/autoload.php';

$tmpDir = __DIR__ . '/../tmp';
if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
    throw new RuntimeException('Cannot create the temporary test directory');
}

$testPrefix = $tmpDir . '/test-access-logger-' . bin2hex(random_bytes(8));
$appLog = $testPrefix . '-app.log';
$accessLog = $testPrefix . '-access.log';
$configFile = $testPrefix . '-config.php';
register_shutdown_function(static function () use ($appLog, $accessLog, $configFile): void {
    @unlink($appLog);
    @unlink($accessLog);
    @unlink($configFile);
});

$config = [
    'settings' => [
        'displayErrorDetails' => false,
        'basePath' => '',
        'logger' => [
            'name' => 'PyroshapeTest',
            'path' => $appLog,
            'level' => Logger::DEBUG,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %level_name% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'accessLogger' => [
            'name' => 'PyroshapeTest',
            'path' => $accessLog,
            'level' => Logger::INFO,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'headers' => [
            'X-Pyroshape' => '1',
            'X-Robots-Tag' => 'noindex, nofollow',
        ],
    ],
];

file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

$app = new App($configFile);
$request = (new ServerRequestFactory())->createServerRequest('GET', '/health', ['REMOTE_ADDR' => '127.0.0.1:54321'])
    ->withHeader('User-Agent', 'PyroshapeSmoke/1.0');
$response = $app->getSlimApp()->handle($request);

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "Expected status 200, got {$response->getStatusCode()}\n");
    exit(1);
}

$appLogContents = is_file($appLog) ? file_get_contents($appLog) : '';
$accessLogContents = is_file($accessLog) ? file_get_contents($accessLog) : '';

if (!str_contains($accessLogContents, '127.0.0.1 "GET /health HTTP/1.1" 200 (OK) "PyroshapeSmoke/1.0"')) {
    fwrite(STDERR, "Access log missing request line:\n$accessLogContents\n");
    exit(1);
}

if (str_contains($appLogContents, '"GET /health HTTP/1.1"')) {
    fwrite(STDERR, "Application log contains access log line:\n$appLogContents\n");
    exit(1);
}

echo "access logger ok\n";
