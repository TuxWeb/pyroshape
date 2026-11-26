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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

require __DIR__ . '/../vendor/autoload.php';

function statelessJsonRequest(string $method, string $uri, ?array $payload = null, ?string $cookie = null): ServerRequestInterface
{
    $request = (new ServerRequestFactory())->createServerRequest($method, $uri)
        ->withHeader('Accept', 'application/json');

    if ($payload !== null) {
        $body = (new StreamFactory())->createStream(json_encode($payload, JSON_THROW_ON_ERROR));
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    if ($cookie !== null && $cookie !== '') {
        $request = $request
            ->withHeader('Cookie', $cookie)
            ->withCookieParams(statelessParseCookieHeader($cookie));
    }

    return $request;
}

function statelessParseCookieHeader(string $cookie): array
{
    $cookies = [];
    foreach (explode(';', $cookie) as $part) {
        $part = trim($part);
        if ($part === '' || !str_contains($part, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $part, 2);
        $cookies[$name] = urldecode($value);
    }

    return $cookies;
}

function statelessResponseJson(ResponseInterface $response): array
{
    $body = (string) $response->getBody();
    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        fwrite(STDERR, "Expected JSON object response, got: $body\n");
        exit(1);
    }

    return $payload;
}

function statelessAssertStatus(ResponseInterface $response, int $expected, string $label): void
{
    if ($response->getStatusCode() !== $expected) {
        fwrite(STDERR, "$label: expected status $expected, got {$response->getStatusCode()}\n");
        fwrite(STDERR, "Response body: " . (string) $response->getBody() . "\n");
        exit(1);
    }
}

function statelessExtractCookie(ResponseInterface $response, string $cookieName, string $label): string
{
    foreach ($response->getHeader('Set-Cookie') as $header) {
        $cookiePair = explode(';', $header, 2)[0];
        if (str_starts_with($cookiePair, $cookieName . '=')) {
            return $cookiePair;
        }
    }

    fwrite(STDERR, "$label: expected Set-Cookie header for \"$cookieName\"\n");
    exit(1);
}

$tmpDir = __DIR__ . '/../tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}

$testId = bin2hex(random_bytes(6));
$sqliteFile = "$tmpDir/test-auth-stateless-$testId.sqlite";
$configFile = "$tmpDir/test-auth-stateless-$testId-config.php";
$loginCookieName = 'pyroshape_stateless';

$config = [
    'settings' => [
        'displayErrorDetails' => false,
        'basePath' => '',
        'logger' => [
            'name' => 'PyroshapeStatelessAuthTest',
            'path' => "$tmpDir/test-auth-stateless-$testId-app.log",
            'level' => Logger::DEBUG,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'accessLogger' => [
            'name' => 'PyroshapeStatelessAuthTest',
            'path' => "$tmpDir/test-auth-stateless-$testId-access.log",
            'level' => Logger::INFO,
            'dateFormat' => 'Y-m-d H:i:s',
            'logFormat' => "%datetime% %message%\n",
            'timezone' => 'Europe/Rome',
        ],
        'headers' => [],
        'database' => [
            'driver' => 'sqlite',
            'dsn' => 'sqlite:' . $sqliteFile,
            'path' => $sqliteFile,
        ],
        'jwt_refresh_token' => [
            'secret' => hash('sha256', 'stateless-refresh-secret'),
            'ttl' => 60 * 60,
            'auto_refresh' => 0,
            'algorithm' => 'HS256',
        ],
        'jwt_access_token' => [
            'secret' => hash('sha256', 'stateless-access-secret'),
            'ttl' => 1,
            'algorithm' => 'HS256',
        ],
        'login_cookie' => [
            'name' => $loginCookieName,
            'ttl' => 60 * 60,
            'path' => '/',
            'secure' => false,
            'httpOnly' => true,
            'sameSite' => 'Lax',
            'key' => hash('sha256', 'stateless-cookie-key'),
            'salt' => 'stateless-cookie-salt',
        ],
        'emailVerification' => [
            'devExposeToken' => true,
        ],
    ],
];

file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

$pdo = new PDO('sqlite:' . $sqliteFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    pwdhash TEXT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    email_verified INTEGER NOT NULL DEFAULT 0,
    nickname TEXT NULL,
    name TEXT NULL,
    surname TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE TABLE auth_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    users_id INTEGER NOT NULL,
    jti TEXT NOT NULL UNIQUE,
    token_hash TEXT NOT NULL,
    released TEXT NOT NULL,
    expiration TEXT NOT NULL,
    revocation_time TEXT NULL
);
CREATE TABLE email_verification_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    users_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    expiration TEXT NOT NULL,
    used_at TEXT NULL
);
SQL);

register_shutdown_function(static function () use ($tmpDir, $testId): void {
    foreach (glob("$tmpDir/test-auth-stateless-$testId-*") ?: [] as $file) {
        @unlink($file);
    }
});

$slim = (new App($configFile))->getSlimApp();
$email = "stateless-$testId@example.test";
$password = 'CorrectHorseBatteryStaple!42';

$registerResponse = $slim->handle(statelessJsonRequest('POST', '/api/auth/register', [
    'email' => $email,
    'password' => $password,
    'nickname' => 'stateless',
]));
statelessAssertStatus($registerResponse, 201, 'register');
$verificationToken = statelessResponseJson($registerResponse)['verificationToken'] ?? '';

$verifyResponse = $slim->handle(statelessJsonRequest('GET', '/api/auth/verify-email?token=' . rawurlencode((string) $verificationToken)));
statelessAssertStatus($verifyResponse, 200, 'verify');

$login = $slim->handle(statelessJsonRequest('POST', '/api/auth/login', [
    'email' => $email,
    'password' => $password,
    'rememberMe' => true,
]));
statelessAssertStatus($login, 200, 'login');
$cookie = statelessExtractCookie($login, $loginCookieName, 'login');
$loginPayload = statelessResponseJson($login);
if (($loginPayload['authenticated'] ?? false) !== true || ($loginPayload['emailVerified'] ?? false) !== true) {
    fwrite(STDERR, "Unexpected login payload: " . json_encode($loginPayload) . "\n");
    exit(1);
}

$tokenCount = (int) $pdo->query('SELECT COUNT(*) FROM auth_tokens')->fetchColumn();
if ($tokenCount !== 1) {
    fwrite(STDERR, "Stateless login should store refresh token rows\n");
    exit(1);
}

$pdo->exec('UPDATE users SET enabled = 0, email_verified = 0 WHERE id = 1');
$session = $slim->handle(statelessJsonRequest('GET', '/api/session', null, $cookie));
statelessAssertStatus($session, 200, 'session from access token');
$sessionPayload = statelessResponseJson($session);
if (($sessionPayload['authenticated'] ?? false) !== true || ($sessionPayload['enabled'] ?? false) !== true || ($sessionPayload['emailVerified'] ?? false) !== true) {
    fwrite(STDERR, "Stateless session did not trust access-token claims: " . json_encode($sessionPayload) . "\n");
    exit(1);
}

$pdo->exec('UPDATE users SET enabled = 1, email_verified = 1 WHERE id = 1');
sleep(2);
$refreshedSession = $slim->handle(statelessJsonRequest('GET', '/api/session', null, $cookie));
statelessAssertStatus($refreshedSession, 200, 'session from refresh token');
$refreshedPayload = statelessResponseJson($refreshedSession);
if (($refreshedPayload['authenticated'] ?? false) !== true || ($refreshedPayload['emailVerified'] ?? false) !== true) {
    fwrite(STDERR, "Stateless refresh did not validate the stored refresh token: " . json_encode($refreshedPayload) . "\n");
    exit(1);
}
statelessExtractCookie($refreshedSession, $loginCookieName, 'refreshed session');

$pdo->exec('UPDATE auth_tokens SET revocation_time = CURRENT_TIMESTAMP');
sleep(2);
$revokedSession = $slim->handle(statelessJsonRequest('GET', '/api/session', null, statelessExtractCookie($refreshedSession, $loginCookieName, 'refreshed session')));
statelessAssertStatus($revokedSession, 200, 'session from revoked refresh token');
$revokedPayload = statelessResponseJson($revokedSession);
if (($revokedPayload['authenticated'] ?? true) !== false) {
    fwrite(STDERR, "Stateless refresh accepted a revoked refresh token: " . json_encode($revokedPayload) . "\n");
    exit(1);
}

echo "auth stateless session ok\n";
