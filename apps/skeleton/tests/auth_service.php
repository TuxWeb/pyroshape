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
use TuxWeb\Pyroshape\Core\Auth\AuthService;
use TuxWeb\Pyroshape\Core\Auth\UserRepository;
use DI\Container;
use Monolog\Logger;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

require __DIR__ . '/../vendor/autoload.php';

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function assertRuntimeException(callable $callback, string $expectedMessage, string $label): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== $expectedMessage) {
            fail("$label: expected RuntimeException message \"$expectedMessage\", got \"{$exception->getMessage()}\"");
        }

        return;
    }

    fail("$label: expected RuntimeException");
}

function requestWithCookie(string $cookieHeader): \Psr\Http\Message\ServerRequestInterface
{
    $cookies = [];
    foreach (explode(';', $cookieHeader) as $part) {
        $part = trim($part);
        if ($part === '' || !str_contains($part, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $part, 2);
        $cookies[$name] = urldecode($value);
    }

    return (new ServerRequestFactory())->createServerRequest('GET', '/')
        ->withHeader('Cookie', $cookieHeader)
        ->withCookieParams($cookies);
}

function assertClearingCookie(\Psr\Http\Message\ResponseInterface $response, string $cookieName, string $label): void
{
    $header = extractSetCookieHeader($response, $cookieName);
    if (!str_contains($header, 'expires=Thu, 01 Jan 1970')) {
        fail("$label: expected clearing Set-Cookie header, got: $header");
    }
}

function assertLog(CapturingLogger $logger, string $level, string $message, array $contextSubset): void
{
    foreach ($logger->records as $record) {
        if ($record['level'] !== $level || $record['message'] !== $message) {
            continue;
        }

        foreach ($contextSubset as $key => $value) {
            if (($record['context'][$key] ?? null) !== $value) {
                continue 2;
            }
        }

        return;
    }

    fail(sprintf(
        'Missing log %s %s with context subset %s. Records: %s',
        $level,
        $message,
        json_encode($contextSubset, JSON_THROW_ON_ERROR),
        json_encode($logger->records, JSON_THROW_ON_ERROR)
    ));
}

function assertLogCount(CapturingLogger $logger, string $level, string $message, int $expected): void
{
    $count = 0;
    foreach ($logger->records as $record) {
        if ($record['level'] === $level && $record['message'] === $message) {
            $count++;
        }
    }

    if ($count !== $expected) {
        fail("Expected $expected log records for $level $message, got $count");
    }
}

function extractCookie(\Psr\Http\Message\ResponseInterface $response, string $cookieName): string
{
    foreach ($response->getHeader('Set-Cookie') as $header) {
        $cookiePair = explode(';', $header, 2)[0];
        if (str_starts_with($cookiePair, $cookieName . '=')) {
            return $cookiePair;
        }
    }

    fail("Missing Set-Cookie header for $cookieName");
}

function extractSetCookieHeader(\Psr\Http\Message\ResponseInterface $response, string $cookieName): string
{
    foreach ($response->getHeader('Set-Cookie') as $header) {
        $cookiePair = explode(';', $header, 2)[0];
        if (str_starts_with($cookiePair, $cookieName . '=')) {
            return $header;
        }
    }

    fail("Missing Set-Cookie header for $cookieName");
}

class FailingRevokeUserRepository extends UserRepository
{
    public function revokeRefreshTokenByJti(string $jti, ?string $revokedAt = null): bool
    {
        throw new RuntimeException('simulated revoke failure');
    }
}

final class CapturingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

$defaultConfigApp = new App(__DIR__ . '/../config/config.php');
$defaultSessionResponse = $defaultConfigApp->getSlimApp()->handle(
    (new ServerRequestFactory())->createServerRequest('GET', '/api/session')
);
$defaultSessionBody = (string) $defaultSessionResponse->getBody();
$defaultSessionPayload = json_decode($defaultSessionBody, true);

if ($defaultSessionResponse->getStatusCode() !== 200) {
    fail("Default no-cookie /api/session expected status 200, got {$defaultSessionResponse->getStatusCode()}: $defaultSessionBody");
}

if ($defaultSessionPayload !== ['authenticated' => false]) {
    fail("Default no-cookie /api/session returned unexpected payload: $defaultSessionBody");
}

$pdo = new PDO('sqlite::memory:');
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

$cookieName = 'auth_service_session';
$logger = new CapturingLogger();
$container = new Container([
    LoggerInterface::class => $logger,
    'settings' => [
        'logger' => [
            'level' => Logger::DEBUG,
        ],
        'auth' => [
            'sessionMode' => 'stateful',
        ],
        'jwt_refresh_token' => [
            'secret' => hash('sha256', 'refresh-token-key'),
            'algorithm' => 'HS256',
            'iss' => 'pyroshape-auth-service-test',
            'time_skew' => 0,
            'ttl' => 3600,
        ],
        'jwt_access_token' => [
            'secret' => hash('sha256', 'access-token-key'),
            'algorithm' => 'HS256',
            'iss' => 'pyroshape-auth-service-test',
            'time_skew' => 0,
            'ttl' => 1,
        ],
        'login_cookie' => [
            'name' => $cookieName,
            'path' => '/',
            'secure' => false,
            'httpOnly' => true,
            'sameSite' => 'Lax',
            'key' => hash('sha256', 'cookie-key'),
            'salt' => 'cookie-salt',
        ],
        'emailVerification' => [
            'expire' => 3600,
            'devExposeToken' => true,
        ],
    ],
]);

$service = new AuthService($container, new UserRepository($pdo));
$responseFactory = new ResponseFactory();

$registration = $service->registerLocalUser('MixedCase@example.TEST', 'CorrectHorseBatteryStaple!42', [
    'nickname' => 'Auth Test',
]);

if (($registration['email'] ?? null) !== 'mixedcase@example.test') {
    fail('Registration did not normalize email');
}

if (($registration['enabled'] ?? null) !== false || ($registration['emailVerified'] ?? null) !== false) {
    fail('Registration did not create a disabled unverified user');
}

$verificationToken = $registration['verificationToken'] ?? null;
if (!is_string($verificationToken) || $verificationToken === '') {
    fail('Registration did not expose verification token in dev mode');
}

assertRuntimeException(
    fn () => $service->login('mixedcase@example.test', 'CorrectHorseBatteryStaple!42', $responseFactory->createResponse()),
    'Email address is not verified',
    'unverified login'
);

$verified = $service->verifyEmailToken($verificationToken);
if (($verified['verified'] ?? null) !== true || ($verified['emailVerified'] ?? null) !== true) {
    fail('Email verification did not verify the user');
}

assertRuntimeException(
    fn () => $service->verifyEmailToken($verificationToken),
    'Verification token is invalid, expired, or already used',
    'verification token reuse'
);

$missingUserLoginStart = hrtime(true);
assertRuntimeException(
    fn () => $service->login('missing@example.test', 'WrongPassword!42', $responseFactory->createResponse()),
    'Invalid email or password',
    'missing user login'
);
$missingUserLoginElapsedMs = (hrtime(true) - $missingUserLoginStart) / 1_000_000;
if ($missingUserLoginElapsedMs < 10.0) {
    fail('Missing-user login did not appear to run bcrypt password verification');
}

$login = $service->login('mixedcase@example.test', 'CorrectHorseBatteryStaple!42', $responseFactory->createResponse());
if (($login['authenticated'] ?? null) !== true || ($login['email'] ?? null) !== 'mixedcase@example.test') {
    fail('Login did not return authenticated user data');
}
assertLog($logger, 'notice', 'auth.token_issued', ['user_id' => (int) $login['id'], 'email' => 'mixedcase@example.test']);

$loginResponse = $login['response'] ?? null;
if (!$loginResponse instanceof \Psr\Http\Message\ResponseInterface) {
    fail('Login did not return updated response');
}

$cookie = extractCookie($loginResponse, $cookieName);
$session = $service->getSession(requestWithCookie($cookie), $responseFactory->createResponse());
if (($session['authenticated'] ?? null) !== true || ($session['email'] ?? null) !== 'mixedcase@example.test') {
    fail('Session cookie did not authenticate the user');
}

$invalidSession = $service->getSession(
    requestWithCookie($cookieName . '=tampered-cookie-value'),
    $responseFactory->createResponse()
);
if (($invalidSession['authenticated'] ?? true) !== false) {
    fail('Invalid session cookie authenticated a session');
}
$invalidSessionResponse = $invalidSession['response'] ?? null;
if (!$invalidSessionResponse instanceof \Psr\Http\Message\ResponseInterface) {
    fail('Invalid session did not return updated response');
}
assertClearingCookie($invalidSessionResponse, $cookieName, 'invalid session cookie');

sleep(2);
$refreshed = $service->getSession(requestWithCookie($cookie), $responseFactory->createResponse());
if (($refreshed['authenticated'] ?? null) !== true || !$refreshed['response'] instanceof \Psr\Http\Message\ResponseInterface) {
    fail('Expired access token did not refresh from refresh token');
}
assertLogCount($logger, 'notice', 'auth.token_issued', 2);

$logout = $service->logout(requestWithCookie($cookie), $responseFactory->createResponse());
if (($logout['authenticated'] ?? true) !== false) {
    fail('Logout did not return unauthenticated state');
}

$logoutResponse = $logout['response'] ?? null;
if (!$logoutResponse instanceof \Psr\Http\Message\ResponseInterface) {
    fail('Logout did not return updated response');
}

$clearCookieHeader = extractSetCookieHeader($logoutResponse, $cookieName);
if (
    !str_contains($clearCookieHeader, $cookieName . '=')
    || !str_contains($clearCookieHeader, 'path=/')
    || !str_contains($clearCookieHeader, 'HttpOnly')
    || !str_contains($clearCookieHeader, 'SameSite=Lax')
    || !str_contains($clearCookieHeader, 'expires=Thu, 01 Jan 1970')
) {
    fail("Logout did not emit expected clearing cookie header: $clearCookieHeader");
}

$afterLogout = $service->getSession(requestWithCookie($cookie), $responseFactory->createResponse());
if (($afterLogout['authenticated'] ?? true) !== false) {
    fail('Revoked refresh token still authenticated a session');
}
assertLog($logger, 'warning', 'auth.refresh_token_rejected', ['reason' => 'Refresh token is not active']);

assertRuntimeException(
    fn () => (new AuthService($container, new FailingRevokeUserRepository($pdo)))
        ->logout(requestWithCookie($cookie), $responseFactory->createResponse()),
    'simulated revoke failure',
    'logout revoke failure'
);

echo "auth service ok\n";
