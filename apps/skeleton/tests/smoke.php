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
use TuxWeb\PyroshapeSkeleton\Exceptions\ConfigNotValidException;
use Slim\Psr7\Factory\ServerRequestFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = new App(__DIR__ . '/../config/config.php');
$slim = $app->getSlimApp();
$request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
$response = $slim->handle($request);

$body = (string) $response->getBody();
$payload = json_decode($body, true);

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "Expected status 200, got {$response->getStatusCode()}\n");
    exit(1);
}

if ($payload !== ['status' => 'ok', 'service' => 'pyroshape']) {
    fwrite(STDERR, "Unexpected health payload: $body\n");
    exit(1);
}

echo "smoke ok\n";

$unsafeConfigFile = __DIR__ . '/../tmp/test-unsafe-secret-config.php';
file_put_contents($unsafeConfigFile, '<?php return ' . var_export([
    'settings' => [
        'environment' => 'production',
        'displayErrorDetails' => false,
        'basePath' => '',
        'logger' => [
            'name' => 'PyroshapeUnsafeSecretTest',
            'path' => __DIR__ . '/../tmp/test-unsafe-secret-app.log',
            'level' => Monolog\Logger::DEBUG,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %level_name% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'accessLogger' => [
            'name' => 'PyroshapeUnsafeSecretTest',
            'path' => __DIR__ . '/../tmp/test-unsafe-secret-access.log',
            'level' => Monolog\Logger::INFO,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'headers' => [],
        'jwt_refresh_token' => [
            'secret' => 'change-me-refresh-secret',
        ],
        'jwt_access_token' => [
            'secret' => 'change-me-access-secret',
        ],
        'login_cookie' => [
            'secret' => 'change-me-cookie-secret',
            'salt' => 'change-me-cookie-salt',
        ],
        'emailVerification' => [
            'devExposeToken' => false,
        ],
    ],
], true) . ';');

try {
    new App($unsafeConfigFile);
} catch (ConfigNotValidException $exception) {
    $message = $exception->getMessage();
    foreach (['jwt_refresh_token.secret', 'jwt_access_token.secret', 'login_cookie.secret', 'login_cookie.salt'] as $setting) {
        if (!str_contains($message, $setting)) {
            fwrite(STDERR, "Unsafe config error missing $setting: $message\n");
            exit(1);
        }
    }

    @unlink($unsafeConfigFile);
    @unlink(__DIR__ . '/../tmp/test-unsafe-secret-app.log');
    @unlink(__DIR__ . '/../tmp/test-unsafe-secret-access.log');
    exit(0);
}

fwrite(STDERR, "Production unsafe secret aliases were accepted\n");
exit(1);
