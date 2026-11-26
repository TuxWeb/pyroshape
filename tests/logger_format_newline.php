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

$tmpDir = __DIR__ . '/../tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}

$testId = bin2hex(random_bytes(6));
$appLog = "$tmpDir/test-logger-format-$testId-app.log";
$accessLog = "$tmpDir/test-logger-format-$testId-access.log";
$configFile = "$tmpDir/test-logger-format-$testId-config.php";

$config = [
    'settings' => [
        'displayErrorDetails' => false,
        'basePath' => '',
        'logger' => [
            'name' => 'PyroshapeLoggerFormatTest',
            'path' => $appLog,
            'level' => Logger::DEBUG,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => '%message%',
        ],
        'accessLogger' => [
            'name' => 'PyroshapeLoggerFormatTest',
            'path' => $accessLog,
            'level' => Logger::INFO,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => '%message%',
        ],
        'headers' => [],
        'jwt_refresh_token' => [
            'secret' => hash('sha256', 'logger-format-refresh-secret'),
        ],
        'jwt_access_token' => [
            'secret' => hash('sha256', 'logger-format-access-secret'),
        ],
        'login_cookie' => [
            'key' => hash('sha256', 'logger-format-cookie-key'),
            'salt' => 'logger-format-cookie-salt',
        ],
    ],
];

file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

register_shutdown_function(static function () use ($tmpDir, $testId): void {
    foreach (glob("$tmpDir/test-logger-format-$testId-*") ?: [] as $file) {
        @unlink($file);
    }
});

new App($configFile);

$log = file_get_contents($appLog);
if ($log === false || !str_ends_with($log, PHP_EOL)) {
    fwrite(STDERR, "Logger format did not append a line break\n");
    exit(1);
}

echo "logger format newline ok\n";
